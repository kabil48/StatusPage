<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\MonitoredService;
use App\Enum\IncidentImpact;
use App\Enum\IncidentStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

/** Déclaration d'un incident manuel : détails + premier message de timeline. */
final class NewIncidentInput
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 5, max: 255)]
    public string $title = '';

    #[Assert\NotNull]
    public IncidentImpact $impact = IncidentImpact::Minor;

    /** @var Collection<int, MonitoredService> */
    #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins un service affecté.')]
    public Collection $affectedServices;

    #[Assert\NotNull]
    #[Assert\NotIdenticalTo(value: IncidentStatus::Resolved, message: 'Un nouvel incident ne peut pas être déjà résolu.')]
    public IncidentStatus $status = IncidentStatus::Investigating;

    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(min: 5, max: 5000, minMessage: 'Le message doit contenir au moins {{ limit }} caractères.')]
    public string $message = '';

    public function __construct()
    {
        $this->affectedServices = new ArrayCollection();
    }
}
