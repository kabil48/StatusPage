<?php

declare(strict_types=1);

namespace App\Exception;

/** RG-INC-02 : un incident résolu est figé. */
final class IncidentAlreadyResolvedException extends \DomainException
{
    public static function forIncident(?int $incidentId): self
    {
        return new self(\sprintf('L\'incident #%s est résolu et ne peut plus être modifié.', $incidentId ?? '?'));
    }
}
