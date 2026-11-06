<?php

declare(strict_types=1);

namespace App\Enum;

enum ServiceStatus: string
{
    case Unknown = 'unknown';
    case Operational = 'operational';
    case Degraded = 'degraded';
    case Down = 'down';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Inconnu',
            self::Operational => 'Opérationnel',
            self::Degraded => 'Dégradé',
            self::Down => 'Indisponible',
            self::Paused => 'En pause',
        };
    }

    /** Couleur Bootstrap associée (badge, barre d'uptime...). */
    public function color(): string
    {
        return match ($this) {
            self::Operational => 'success',
            self::Degraded => 'warning',
            self::Down => 'danger',
            self::Unknown, self::Paused => 'secondary',
        };
    }

    /** Un service "up" au sens de la disponibilité (RG-STATS-01 : degraded compte comme UP). */
    public function isUp(): bool
    {
        return self::Operational === $this || self::Degraded === $this;
    }
}
