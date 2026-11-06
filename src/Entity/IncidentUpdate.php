<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\IncidentStatus;
use App\Repository\IncidentUpdateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entrée de la timeline d'un incident. Créée uniquement via Incident::addUpdate().
 */
#[ORM\Entity(repositoryClass: IncidentUpdateRepository::class)]
#[ORM\Index(name: 'idx_update_incident', columns: ['incident_id', 'created_at'])]
class IncidentUpdate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'updates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Incident $incident;

    /** NULL = message généré par le système (incident automatique). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author;

    #[ORM\Column(length: 20, enumType: IncidentStatus::class)]
    private IncidentStatus $status;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Incident $incident,
        IncidentStatus $status,
        string $message,
        ?User $author,
        \DateTimeImmutable $createdAt,
    ) {
        $this->incident = $incident;
        $this->status = $status;
        $this->message = $message;
        $this->author = $author;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIncident(): Incident
    {
        return $this->incident;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function isSystemGenerated(): bool
    {
        return null === $this->author;
    }

    public function getStatus(): IncidentStatus
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
