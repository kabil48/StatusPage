<?php

declare(strict_types=1);

namespace App\Service\Incident;

use App\Entity\Incident;
use App\Entity\IncidentUpdate;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\IncidentImpact;
use App\Enum\IncidentStatus;
use App\Exception\IncidentAlreadyResolvedException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Cas d'usage des incidents manuels (RG-INC-01 à 04).
 * Point d'entrée unique partagé par l'interface web et l'API.
 */
final class IncidentManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Déclare un incident avec son premier message de timeline.
     *
     * @param iterable<MonitoredService> $affectedServices au moins un, du même projet (RG-INC-01)
     */
    public function open(
        Project $project,
        string $title,
        IncidentImpact $impact,
        iterable $affectedServices,
        IncidentStatus $status,
        string $message,
        User $author,
    ): Incident {
        $now = $this->clock->now();
        $incident = new Incident($project, $title, $impact, false, $now);

        foreach ($affectedServices as $service) {
            $incident->addAffectedService($service);
        }
        if ($incident->getAffectedServices()->isEmpty()) {
            throw new \InvalidArgumentException('Un incident doit affecter au moins un service.');
        }

        $incident->addUpdate($status, $message, $author, $now);

        $this->entityManager->persist($incident);
        $this->entityManager->flush();

        return $incident;
    }

    /**
     * Ajoute une entrée à la timeline (et fait évoluer le statut, RG-INC-02/03).
     *
     * @throws IncidentAlreadyResolvedException si l'incident est déjà résolu
     */
    public function addUpdate(Incident $incident, IncidentStatus $status, string $message, User $author): IncidentUpdate
    {
        $update = $incident->addUpdate($status, $message, $author, $this->clock->now());
        $this->entityManager->flush();

        return $update;
    }

    /** @throws IncidentAlreadyResolvedException */
    public function resolve(Incident $incident, string $message, User $author): IncidentUpdate
    {
        return $this->addUpdate($incident, IncidentStatus::Resolved, $message, $author);
    }

    public function delete(Incident $incident): void
    {
        $this->entityManager->remove($incident);
        $this->entityManager->flush();
    }
}
