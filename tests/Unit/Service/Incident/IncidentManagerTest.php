<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Incident;

use App\Entity\Incident;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\IncidentImpact;
use App\Enum\IncidentStatus;
use App\Exception\IncidentAlreadyResolvedException;
use App\Service\Incident\IncidentManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class IncidentManagerTest extends TestCase
{
    private IncidentManager $manager;
    private MockClock $clock;
    private User $author;
    private Project $project;
    private MonitoredService $service;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-06 10:00:00');
        $this->manager = new IncidentManager($this->createStub(EntityManagerInterface::class), $this->clock);
        $this->author = new User('owner@example.com', 'Owner');
        $this->project = new Project($this->author, 'Demo', 'demo');
        $this->service = new MonitoredService($this->project, 'API', 'https://example.com');
    }

    public function testOpenCreatesIncidentWithFirstTimelineEntry(): void
    {
        $incident = $this->open();

        self::assertFalse($incident->isAutoCreated());
        self::assertSame(IncidentStatus::Identified, $incident->getStatus());
        self::assertEquals($this->clock->now(), $incident->getStartedAt());
        self::assertTrue($incident->affects($this->service));
        self::assertCount(1, $incident->getUpdates());
        self::assertSame($this->author, $incident->getUpdates()->first()->getAuthor());
    }

    public function testOpenRequiresAtLeastOneService(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->open($this->project, 'Panne API', IncidentImpact::Major, [], IncidentStatus::Investigating, 'Nous enquêtons.', $this->author);
    }

    public function testOpenRejectsServiceFromAnotherProject(): void
    {
        $other = new MonitoredService(new Project($this->author, 'Autre', 'autre'), 'Autre API', 'https://example.org');

        $this->expectException(\InvalidArgumentException::class);

        $this->manager->open($this->project, 'Panne API', IncidentImpact::Major, [$other], IncidentStatus::Investigating, 'Nous enquêtons.', $this->author);
    }

    public function testResolveSetsResolvedAt(): void
    {
        $incident = $this->open();
        $this->clock->sleep(1800);

        $this->manager->resolve($incident, 'Correctif déployé.', $this->author);

        self::assertSame(IncidentStatus::Resolved, $incident->getStatus());
        self::assertEquals($this->clock->now(), $incident->getResolvedAt());
        self::assertSame(1800, $incident->getDurationInSeconds());
    }

    /** U-13 */
    public function testUpdatingResolvedIncidentThrows(): void
    {
        $incident = $this->open();
        $this->manager->resolve($incident, 'Correctif déployé.', $this->author);

        $this->expectException(IncidentAlreadyResolvedException::class);

        $this->manager->addUpdate($incident, IncidentStatus::Monitoring, 'On réouvre ?', $this->author);
    }

    private function open(): Incident
    {
        return $this->manager->open(
            $this->project,
            'Lenteurs sur l\'API',
            IncidentImpact::Minor,
            [$this->service],
            IncidentStatus::Identified,
            'Base de données saturée, correctif en cours.',
            $this->author,
        );
    }
}
