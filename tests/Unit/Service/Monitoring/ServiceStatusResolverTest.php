<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring;

use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ServiceStatus;
use App\Service\Monitoring\CheckResult;
use App\Service\Monitoring\ServiceStatusResolver;
use PHPUnit\Framework\TestCase;

final class ServiceStatusResolverTest extends TestCase
{
    private ServiceStatusResolver $resolver;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->resolver = new ServiceStatusResolver(recoveryThreshold: 2);
        $this->now = new \DateTimeImmutable('2026-11-06 10:00:00');
    }

    /** U-05 */
    public function testSingleFailureBelowThresholdKeepsStatus(): void
    {
        $service = $this->service(ServiceStatus::Operational);

        self::assertSame(ServiceStatus::Operational, $this->resolver->resolve($service, $this->down()));
        self::assertSame(1, $service->getConsecutiveFailures());
    }

    public function testFirstFailureOnUnknownServiceIsDegraded(): void
    {
        self::assertSame(ServiceStatus::Degraded, $this->resolver->resolve($this->service(), $this->down()));
    }

    /** U-06 */
    public function testThresholdConsecutiveFailuresMeansDown(): void
    {
        $service = $this->service(ServiceStatus::Operational);

        $this->resolver->resolve($service, $this->down());
        $this->resolver->resolve($service, $this->down());
        self::assertSame(ServiceStatus::Down, $this->resolver->resolve($service, $this->down()));
        self::assertSame(3, $service->getConsecutiveFailures());
    }

    public function testSuccessResetsFailureCounter(): void
    {
        $service = $this->service(ServiceStatus::Operational);

        $this->resolver->resolve($service, $this->down());
        $this->resolver->resolve($service, $this->down());
        $this->resolver->resolve($service, $this->up());
        $this->resolver->resolve($service, $this->down());

        self::assertSame(ServiceStatus::Operational, $service->getCurrentStatus());
        self::assertSame(1, $service->getConsecutiveFailures());
    }

    /** U-07 */
    public function testDownServiceNeedsTwoSuccessesToRecover(): void
    {
        $service = $this->service(ServiceStatus::Down);

        self::assertSame(ServiceStatus::Down, $this->resolver->resolve($service, $this->up()));
        self::assertSame(ServiceStatus::Operational, $this->resolver->resolve($service, $this->up()));
    }

    /** U-08 */
    public function testSlowResponseIsDegraded(): void
    {
        $service = $this->service(ServiceStatus::Operational);
        $service->setDegradedThresholdMs(2000);

        self::assertSame(ServiceStatus::Degraded, $this->resolver->resolve($service, $this->up(3000)));
    }

    public function testCheckSchedulesNextRun(): void
    {
        $service = $this->service();
        $this->resolver->resolve($service, $this->up());

        self::assertEquals($this->now, $service->getLastCheckedAt());
        self::assertEquals($this->now->modify('+300 seconds'), $service->getNextCheckAt());
    }

    private function service(ServiceStatus $status = ServiceStatus::Unknown): MonitoredService
    {
        $project = new Project(new User('owner@example.com', 'Owner'), 'Demo', 'demo');
        $service = new MonitoredService($project, 'API', 'https://example.com');
        $service->setFailureThreshold(3);
        $service->setCurrentStatus($status);

        return $service;
    }

    private function up(int $ms = 100): CheckResult
    {
        return new CheckResult(true, $this->now, 200, $ms);
    }

    private function down(): CheckResult
    {
        return new CheckResult(false, $this->now, 500, 100, 'Code HTTP 500 reçu, 200 attendu');
    }
}
