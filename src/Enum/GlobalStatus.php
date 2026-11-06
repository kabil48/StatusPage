<?php

declare(strict_types=1);

namespace App\Enum;

/** Statut global d'un projet, affiché en tête de la page publique. */
enum GlobalStatus: string
{
    case Operational = 'operational';
    case Degraded = 'degraded';
    case PartialOutage = 'partial_outage';
    case MajorOutage = 'major_outage';
    case NoData = 'no_data';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Tous les systèmes sont opérationnels',
            self::Degraded => 'Performances dégradées',
            self::PartialOutage => 'Panne partielle',
            self::MajorOutage => 'Panne majeure',
            self::NoData => 'Statut en cours de collecte',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Operational => 'success',
            self::Degraded => 'warning',
            self::PartialOutage => 'orange',
            self::MajorOutage => 'danger',
            self::NoData => 'secondary',
        };
    }
}
