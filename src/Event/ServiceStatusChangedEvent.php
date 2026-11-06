<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\MonitoredService;
use App\Enum\ServiceStatus;

/** Événement de domaine émis quand le statut d'un service change (RG-INC-AUTO-04). */
final readonly class ServiceStatusChangedEvent
{
    public function __construct(
        public MonitoredService $service,
        public ServiceStatus $previousStatus,
        public ServiceStatus $newStatus,
        public \DateTimeImmutable $occurredAt,
        public ?string $reason = null,
    ) {
    }

    public function wentDown(): bool
    {
        return ServiceStatus::Down === $this->newStatus && ServiceStatus::Down !== $this->previousStatus;
    }

    public function recovered(): bool
    {
        return ServiceStatus::Down === $this->previousStatus && $this->newStatus->isUp();
    }
}
