<?php

declare(strict_types=1);

namespace App\Enum;

enum IncidentImpact: string
{
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Minor => 'Mineur',
            self::Major => 'Majeur',
            self::Critical => 'Critique',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Minor => 'warning',
            self::Major => 'orange',
            self::Critical => 'danger',
        };
    }
}
