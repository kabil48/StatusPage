<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Incident;

use App\Entity\Incident;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\IncidentStatus;
use App\Enum\ServiceStatus;
use App\Event\ServiceStatusChangedEvent;
use App\Repository\IncidentRepository;
use App\Service\Incident\IncidentAutoManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IncidentAutoManagerTest extends TestCase
{
    private IncidentRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private IncidentAutoManager $manager;
    private MonitoredService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(IncidentRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->manager = new IncidentAutoManager($this->repository, $this->entityManager);

        $project = new Project(new User('owner@example.com', 'Owner'), 'Demo', 'demo');
        $this->service = new MonitoredService($project, 'API', 'https://example.com');
    }

    /** U-11 */
    public function testServiceGoingDownOpensAutoIncident(): void
    {
        $this->repository->method('findOpenAutoIncidentForService')->willReturn(null);
        $this->entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Incident::class));

        $incident = $this->manager->handle($this->event(ServiceStatus::Operational, ServiceStatus::Down, 'Code HTTP 503 reçu'));

        self::assertNotNull($incident);
        self::assertTrue($incident->isAutoCreated());
        self::assertSame(IncidentStatus::Investigating, $incident->getStatus());
        self::assertSame('API est indisponible', $incident->getTitle());
        self::assertTrue($incident->affects($this->service));
        self::assertCount(1, $incident->getUpdates());
        self::assertTrue($incident->getUpdates()->first()->isSystemGenerated());
        self::assertStringContainsString('Code HTTP 503 reçu', $incident->getUpdates()->first()->getMessage());
    }

    /** U-11 */
    public function testNoDuplicateWhenAutoIncidentAlreadyOpen(): void
    {
        $this->repository->method('findOpenAutoIncidentForService')->willReturn($this->openAutoIncident());
        $this->entityManager->expects(self::never())->method('persist');

        self::assertNull($this->manager->handle($this->event(ServiceStatus::Degraded, ServiceStatus::Down)));
    }

    /** U-12 */
    public function testRecoveryResolvesAutoIncident(): void
    {
        $incident = $this->openAutoIncident(new \DateTimeImmutable('2026-11-06 09:45:00'));
        $this->repository->method('findOpenAutoIncidentForService')->willReturn($incident);

        $this->manager->handle($this->event(ServiceStatus::Down, ServiceStatus::Operational));

        self::assertSame(IncidentStatus::Resolved, $incident->getStatus());
        self::assertEquals(new \DateTimeImmutable('2026-11-06 10:00:00'), $incident->getResolvedAt());
        self::assertSame('Le service est de nouveau accessible. Durée : 15 min', $incident->getUpdates()->first()->getMessage());
    }

    /** U-12 : le repository ne renvoie que les incidents automatiques, un incident manuel n'est donc jamais touché. */
    public function testRecoveryLeavesManualIncidentUntouched(): void
    {
        $manual = new Incident($this->service->getProject(), 'Maintenance base de données');
        $manual->addAffectedService($this->service);
        $this->repository->method('findOpenAutoIncidentForService')->willReturn(null);

        self::assertNull($this->manager->handle($this->event(ServiceStatus::Down, ServiceStatus::Operational)));
        self::assertTrue($manual->isOpen());
    }

    public function testNonDownTransitionsAreIgnored(): void
    {
        $this->repository->expects(self::never())->method('findOpenAutoIncidentForService');

        self::assertNull($this->manager->handle($this->event(ServiceStatus::Operational, ServiceStatus::Degraded)));
        self::assertNull($this->manager->handle($this->event(ServiceStatus::Unknown, ServiceStatus::Operational)));
    }

    private function event(ServiceStatus $from, ServiceStatus $to, ?string $reason = null): ServiceStatusChangedEvent
    {
        return new ServiceStatusChangedEvent($this->service, $from, $to, new \DateTimeImmutable('2026-11-06 10:00:00'), $reason);
    }

    private function openAutoIncident(?\DateTimeImmutable $startedAt = null): Incident
    {
        $incident = new Incident($this->service->getProject(), 'API est indisponible', isAutoCreated: true, startedAt: $startedAt);
        $incident->addAffectedService($this->service);

        return $incident;
    }
}
