<?php

declare(strict_types=1);

namespace App\Service\Incident;

use App\Entity\Incident;
use App\Enum\IncidentImpact;
use App\Enum\IncidentStatus;
use App\Event\ServiceStatusChangedEvent;
use App\Repository\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ouvre et résout les incidents automatiques (RG-INC-AUTO-01 à 03).
 * Ne flush pas : l'appelant (moteur de monitoring) valide la transaction.
 */
final class IncidentAutoManager
{
    public function __construct(
        private readonly IncidentRepository $incidentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function handle(ServiceStatusChangedEvent $event): ?Incident
    {
        return match (true) {
            $event->wentDown() => $this->open($event),
            $event->recovered() => $this->resolve($event),
            default => null,
        };
    }

    private function open(ServiceStatusChangedEvent $event): ?Incident
    {
        $service = $event->service;
        if (null !== $this->incidentRepository->findOpenAutoIncidentForService($service)) {
            return null; // pas de doublon
        }

        $incident = new Incident(
            $service->getProject(),
            \sprintf('%s est indisponible', $service->getName()),
            IncidentImpact::Major,
            isAutoCreated: true,
            startedAt: $event->occurredAt,
        );
        $incident->addAffectedService($service);

        $message = 'Le service ne répond plus correctement. Nous enquêtons.';
        if (null !== $event->reason) {
            $message .= \sprintf(' Dernière erreur : %s', $event->reason);
        }
        $incident->addUpdate(IncidentStatus::Investigating, $message, null, $event->occurredAt);

        $this->entityManager->persist($incident);

        return $incident;
    }

    private function resolve(ServiceStatusChangedEvent $event): ?Incident
    {
        // Seuls les incidents automatiques sont concernés : les manuels ne sont jamais résolus ici (RG-INC-AUTO-03).
        $incident = $this->incidentRepository->findOpenAutoIncidentForService($event->service);
        if (null === $incident) {
            return null;
        }

        $minutes = (int) ceil($incident->getDurationInSeconds($event->occurredAt) / 60);
        $incident->resolve(\sprintf('Le service est de nouveau accessible. Durée : %d min', $minutes), null, $event->occurredAt);

        return $incident;
    }
}
