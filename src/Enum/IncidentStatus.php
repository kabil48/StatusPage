<?php

declare(strict_types=1);

namespace App\Enum;

/** Workflow : investigating → identified → monitoring → resolved (RG-INC-02). */
enum IncidentStatus: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Investigating => 'Investigation en cours',
            self::Identified => 'Cause identifiée',
            self::Monitoring => 'Sous surveillance',
            self::Resolved => 'Résolu',
        };
    }

    public function isOpen(): bool
    {
        return self::Resolved !== $this;
    }
}
