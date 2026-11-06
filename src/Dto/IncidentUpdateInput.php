<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\IncidentStatus;
use Symfony\Component\Validator\Constraints as Assert;

/** Nouvelle entrée de timeline : statut + message (RG-INC-03). */
final class IncidentUpdateInput
{
    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(min: 5, max: 5000, minMessage: 'Le message doit contenir au moins {{ limit }} caractères.')]
    public string $message = '';

    public function __construct(
        #[Assert\NotNull(message: 'Choisissez un statut.')]
        public ?IncidentStatus $status = null,
    ) {
    }
}
