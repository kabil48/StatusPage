<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Incident;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\IncidentStatus;
use App\Factory\MonitoredServiceFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use App\Repository\IncidentRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

final class IncidentControllerTest extends WebTestCase
{
    use Factories;

    private KernelBrowser $client;
    private User $owner;
    private Project $project;
    private MonitoredService $service;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->owner = UserFactory::createOne();
        $this->project = ProjectFactory::createOne(['owner' => $this->owner, 'slug' => 'acme']);
        $this->service = MonitoredServiceFactory::createOne(['project' => $this->project, 'name' => 'API']);
    }

    public function testDeclareUpdateAndResolveIncident(): void
    {
        $this->client->loginUser($this->owner);
        $base = \sprintf('/dashboard/projects/%d/incidents', $this->project->getId());

        // Déclaration
        $this->client->request('GET', $base.'/new');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Publier l\'incident', [
            'new_incident[title]' => 'Lenteurs sur l\'API',
            'new_incident[impact]' => 'minor',
            'new_incident[status]' => 'investigating',
            'new_incident[affectedServices]' => [(string) $this->service->getId()],
            'new_incident[message]' => 'Nous constatons des lenteurs.',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Lenteurs sur l\'API');

        $incident = $this->findIncident();
        self::assertFalse($incident->isAutoCreated());
        self::assertSame(IncidentStatus::Investigating, $incident->getStatus());

        // Mise à jour intermédiaire
        $this->client->submitForm('Publier', [
            'incident_update[status]' => 'identified',
            'incident_update[message]' => 'Cause identifiée : base saturée.',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();

        // Résolution
        $this->client->submitForm('Publier', [
            'incident_update[status]' => 'resolved',
            'incident_update[message]' => 'Correctif déployé.',
        ]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Incident résolu');

        $incident = $this->findIncident();
        self::assertSame(IncidentStatus::Resolved, $incident->getStatus());
        self::assertNotNull($incident->getResolvedAt());
        self::assertCount(3, $incident->getUpdates());
    }

    public function testInvalidUpdateIsRejected(): void
    {
        $incident = $this->createIncident();
        $this->client->loginUser($this->owner);

        $this->client->request('GET', $this->incidentUrl($incident));
        $this->client->submitForm('Publier', [
            'incident_update[status]' => 'identified',
            'incident_update[message]' => 'ok',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(IncidentStatus::Investigating, $this->findIncident()->getStatus());
    }

    /** RG-INC-02 : un incident résolu est figé (web). */
    public function testResolvedIncidentIsFrozen(): void
    {
        $incident = $this->createIncident(resolved: true);
        $this->client->loginUser($this->owner);

        $this->client->request('GET', $this->incidentUrl($incident));
        self::assertSelectorNotExists('form[name="incident_update"]');

        $this->client->request('GET', $this->incidentUrl($incident).'/edit');
        self::assertResponseRedirects($this->incidentUrl($incident));
    }

    /** F-03 (web) : un autre utilisateur obtient une 404. */
    public function testOtherUserGetsNotFound(): void
    {
        $incident = $this->createIncident();
        $this->client->loginUser(UserFactory::createOne());

        $this->client->request('GET', $this->incidentUrl($incident));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', \sprintf('/dashboard/projects/%d/incidents/new', $this->project->getId()));
        self::assertResponseStatusCodeSame(404);
    }

    public function testIncidentUnderWrongProjectIsNotFound(): void
    {
        $incident = $this->createIncident();
        $otherProject = ProjectFactory::createOne(['owner' => $this->owner]);
        $this->client->loginUser($this->owner);

        $this->client->request('GET', \sprintf('/dashboard/projects/%d/incidents/%d', $otherProject->getId(), $incident->getId()));
        self::assertResponseStatusCodeSame(404);
    }

    public function testPublicPagesShowTimelineWithoutPrivateServices(): void
    {
        $private = MonitoredServiceFactory::createOne(['project' => $this->project, 'name' => 'Back-office interne', 'isPublic' => false]);
        $incident = $this->createIncident(extraService: $private);

        $this->client->request('GET', '/status/acme/incidents');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Panne de l\'API');

        $crawler = $this->client->request('GET', '/status/acme/incidents/'.$incident->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.timeline', 'Nous enquêtons.');
        self::assertStringNotContainsString('Back-office interne', $crawler->text());
        self::assertStringNotContainsString($this->owner->getDisplayName(), $crawler->text());
    }

    public function testPublicIncidentOfAnotherProjectIsNotFound(): void
    {
        $incident = $this->createIncident();
        ProjectFactory::createOne(['slug' => 'autre']);

        $this->client->request('GET', '/status/autre/incidents/'.$incident->getId());
        self::assertResponseStatusCodeSame(404);
    }

    private function createIncident(bool $resolved = false, ?MonitoredService $extraService = null): Incident
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $project = $em->find(Project::class, $this->project->getId());
        $service = $em->find(MonitoredService::class, $this->service->getId());
        \assert($project instanceof Project && $service instanceof MonitoredService);

        $incident = new Incident($project, 'Panne de l\'API');
        $incident->addAffectedService($service);
        if (null !== $extraService) {
            $extra = $em->find(MonitoredService::class, $extraService->getId());
            \assert($extra instanceof MonitoredService);
            $incident->addAffectedService($extra);
        }
        $incident->addUpdate(IncidentStatus::Investigating, 'Nous enquêtons.');
        if ($resolved) {
            $incident->resolve('Résolu.');
        }

        $em->persist($incident);
        $em->flush();

        return $incident;
    }

    private function findIncident(): Incident
    {
        $repository = static::getContainer()->get(IncidentRepository::class);
        $repository->createQueryBuilder('i')->getEntityManager()->clear();
        $incident = $repository->findOneBy(['project' => $this->project->getId()]);
        self::assertInstanceOf(Incident::class, $incident);

        return $incident;
    }

    private function incidentUrl(Incident $incident): string
    {
        return \sprintf('/dashboard/projects/%d/incidents/%d', $this->project->getId(), $incident->getId());
    }
}
