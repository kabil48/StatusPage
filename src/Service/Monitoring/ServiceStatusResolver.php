<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Entity\MonitoredService;
use App\Enum\ServiceStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Applique un résultat de check à un service et calcule son nouveau statut (RG-STATUS-01 à 05).
 */
final class ServiceStatusResolver
{
    public function __construct(
        #[Autowire(param: 'app.recovery_threshold')]
        private readonly int $recoveryThreshold = 2,
    ) {
    }

    public function resolve(MonitoredService $service, CheckResult $result): ServiceStatus
    {
        $previous = $service->getCurrentStatus();
        $service->recordCheck($result->isUp, $result->checkedAt);

        $status = $result->isUp
            ? $this->resolveUp($service, $previous, $result)
            : $this->resolveDown($service, $previous);

        $service->setCurrentStatus($status);

        return $status;
    }

    private function resolveUp(MonitoredService $service, ServiceStatus $previous, CheckResult $result): ServiceStatus
    {
        // RG-STATUS-05 : anti-flapping, un service down doit enchaîner N succès pour être rétabli.
        if (ServiceStatus::Down === $previous && $service->getConsecutiveSuccesses() < $this->recoveryThreshold) {
            return ServiceStatus::Down;
        }

        // RG-STATUS-04
        if (null !== $result->responseTimeMs && $result->responseTimeMs > $service->getDegradedThresholdMs()) {
            return ServiceStatus::Degraded;
        }

        return ServiceStatus::Operational;
    }

    private function resolveDown(MonitoredService $service, ServiceStatus $previous): ServiceStatus
    {
        // RG-STATUS-02
        if ($service->getConsecutiveFailures() >= $service->getFailureThreshold()) {
            return ServiceStatus::Down;
        }

        // RG-STATUS-03 : échec isolé, on conserve le statut (un service inconnu devient dégradé).
        return match ($previous) {
            ServiceStatus::Unknown, ServiceStatus::Paused => ServiceStatus::Degraded,
            default => $previous,
        };
    }
}
