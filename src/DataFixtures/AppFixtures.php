<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Incident;
use App\Entity\MonitorCheck;
use App\Entity\MonitoredService;
use App\Enum\CheckInterval;
use App\Enum\IncidentImpact;
use App\Enum\IncidentStatus;
use App\Enum\ServiceStatus;
use App\Factory\MonitoredServiceFactory;
use App\Factory\ProjectFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Données de démonstration : `php bin/console doctrine:fixtures:load`.
 *
 *  - demo@kabivox.dev / password123 (utilisateur)
 *  - admin@kabivox.dev / password123 (administrateur)
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        UserFactory::new()->admin()->create(['email' => 'admin@kabivox.dev', 'displayName' => 'Admin']);
        $demo = UserFactory::createOne(['email' => 'demo@kabivox.dev', 'displayName' => 'Démo']);

        $project = ProjectFactory::createOne([
            'owner' => $demo,
            'name' => 'Acme Cloud',
            'slug' => 'acme-cloud',
            'description' => 'Statut des services de la plateforme Acme Cloud.',
        ]);

        $services = [
            ['Site web', 'https://example.com', ServiceStatus::Operational, 0.999],
            ['API publique', 'https://httpbin.org/status/200', ServiceStatus::Operational, 0.995],
            ['Paiement', 'https://httpbin.org/delay/3', ServiceStatus::Degraded, 0.97],
            ['Webhooks', 'https://httpbin.org/status/503', ServiceStatus::Down, 0.85],
        ];

        $now = new \DateTimeImmutable();

        foreach ($services as $position => [$name, $url, $status, $uptime]) {
            /** @var MonitoredService $service */
            $service = MonitoredServiceFactory::createOne([
                'project' => $project,
                'name' => $name,
                'url' => $url,
                'position' => $position,
                'checkInterval' => CheckInterval::FiveMinutes,
            ]);

            $this->seedChecks($manager, $service, $now, $uptime);
            $service->setCurrentStatus($status);
        }

        // Incident en cours (automatique) sur les webhooks
        $webhooks = $project->getServices()->last();
        \assert($webhooks instanceof MonitoredService);
        $open = new Incident($project, 'Webhooks est indisponible', IncidentImpact::Major, true, $now->modify('-40 minutes'));
        $open->addAffectedService($webhooks);
        $open->addUpdate(IncidentStatus::Investigating, 'Panne détectée automatiquement : 3 échecs consécutifs (HTTP 503).', null, $now->modify('-40 minutes'));
        $open->addUpdate(IncidentStatus::Identified, 'Le cluster de files de messages est saturé, une intervention est en cours.', $demo, $now->modify('-25 minutes'));
        $manager->persist($open);

        // Incident résolu (manuel) il y a 3 jours
        $site = $project->getServices()->first();
        \assert($site instanceof MonitoredService);
        $start = $now->modify('-3 days -2 hours');
        $resolved = new Incident($project, 'Lenteurs sur le site web', IncidentImpact::Minor, false, $start);
        $resolved->addAffectedService($site);
        $resolved->addUpdate(IncidentStatus::Investigating, 'Nous constatons des temps de chargement élevés.', $demo, $start);
        $resolved->addUpdate(IncidentStatus::Monitoring, 'Un correctif a été déployé, nous surveillons la situation.', $demo, $start->modify('+35 minutes'));
        $resolved->resolve('Les temps de réponse sont revenus à la normale.', $demo, $start->modify('+1 hour'));
        $manager->persist($resolved);

        // Un second projet, privé
        $internal = ProjectFactory::createOne(['owner' => $demo, 'name' => 'Outils internes', 'slug' => 'outils-internes', 'isPublic' => false]);
        MonitoredServiceFactory::createOne(['project' => $internal, 'name' => 'Intranet', 'url' => 'https://example.org']);

        $manager->flush();
    }

    /** Historique de 7 jours de checks (1 par heure) avec le taux de disponibilité souhaité. */
    private function seedChecks(ObjectManager $manager, MonitoredService $service, \DateTimeImmutable $now, float $uptime): void
    {
        for ($hour = 7 * 24; $hour >= 1; --$hour) {
            $isUp = mt_rand() / mt_getrandmax() < $uptime;
            $manager->persist(new MonitorCheck(
                $service,
                $now->modify(\sprintf('-%d hours', $hour)),
                $isUp,
                $isUp ? 200 : 503,
                $isUp ? random_int(80, 900) : random_int(100, 300),
                $isUp ? null : 'HTTP 503 Service Unavailable',
            ));
            $service->recordCheck($isUp, $now->modify(\sprintf('-%d hours', $hour)));
        }
    }
}
