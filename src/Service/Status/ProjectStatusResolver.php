<?php

declare(strict_types=1);

namespace App\Service\Status;

use App\Entity\MonitoredService;
use App\Enum\GlobalStatus;
use App\Enum\ServiceStatus;

/**
 * Calcule le statut global d'un projet = le "pire" de ses services surveillés (cahier des charges §4.2).
 * Les services en pause ou jamais vérifiés sont ignorés.
 */
final class ProjectStatusResolver
{
    /** @param iterable<MonitoredService> $services */
    public function resolve(iterable $services): GlobalStatus
    {
        $statuses = [];
        foreach ($services as $service) {
            $status = $service->getCurrentStatus();
            if (ServiceStatus::Paused !== $status && ServiceStatus::Unknown !== $status) {
                $statuses[] = $status;
            }
        }

        return $this->resolveFromStatuses($statuses);
    }

    /** @param list<ServiceStatus> $statuses statuts "connus" (ni pause, ni inconnu) */
    public function resolveFromStatuses(array $statuses): GlobalStatus
    {
        if ([] === $statuses) {
            return GlobalStatus::NoData;
        }

        $downCount = \count(array_filter($statuses, static fn (ServiceStatus $s) => ServiceStatus::Down === $s));

        return match (true) {
            $downCount === \count($statuses) => GlobalStatus::MajorOutage,
            $downCount > 0 => GlobalStatus::PartialOutage,
            \in_array(ServiceStatus::Degraded, $statuses, true) => GlobalStatus::Degraded,
            default => GlobalStatus::Operational,
        };
    }
}
