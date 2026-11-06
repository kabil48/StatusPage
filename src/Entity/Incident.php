<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\IncidentImpact;
use App\Enum\IncidentStatus;
use App\Exception\IncidentAlreadyResolvedException;
use App\Repository\IncidentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: IncidentRepository::class)]
#[ORM\Index(name: 'idx_incident_project_status', columns: ['project_id', 'status'])]
#[ORM\Index(name: 'idx_incident_project_start', columns: ['project_id', 'started_at'])]
class Incident
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'incidents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    private string $title;

    #[ORM\Column(length: 20, enumType: IncidentStatus::class)]
    private IncidentStatus $status = IncidentStatus::Investigating;

    #[ORM\Column(length: 20, enumType: IncidentImpact::class)]
    private IncidentImpact $impact = IncidentImpact::Major;

    #[ORM\Column]
    private bool $isAutoCreated = false;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, MonitoredService> */
    #[ORM\ManyToMany(targetEntity: MonitoredService::class)]
    #[ORM\JoinTable(name: 'incident_service')]
    #[ORM\JoinColumn(name: 'incident_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'service_id', onDelete: 'CASCADE')]
    #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins un service affecté.')]
    private Collection $affectedServices;

    /** @var Collection<int, IncidentUpdate> */
    #[ORM\OneToMany(targetEntity: IncidentUpdate::class, mappedBy: 'incident', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => \SortDirection::Descending, 'id' => \SortDirection::Descending])]
    private Collection $updates;

    public function __construct(
        Project $project,
        string $title,
        IncidentImpact $impact = IncidentImpact::Major,
        bool $isAutoCreated = false,
        ?\DateTimeImmutable $startedAt = null,
    ) {
        $this->project = $project;
        $this->title = $title;
        $this->impact = $impact;
        $this->isAutoCreated = $isAutoCreated;
        $this->startedAt = $startedAt ?? new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->affectedServices = new ArrayCollection();
        $this->updates = new ArrayCollection();
        $project->addIncident($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->assertNotResolved();
        $this->title = $title;

        return $this;
    }

    public function getStatus(): IncidentStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isResolved(): bool
    {
        return !$this->isOpen();
    }

    public function getImpact(): IncidentImpact
    {
        return $this->impact;
    }

    public function setImpact(IncidentImpact $impact): static
    {
        $this->assertNotResolved();
        $this->impact = $impact;

        return $this;
    }

    public function isAutoCreated(): bool
    {
        return $this->isAutoCreated;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    /** Durée de l'incident (jusqu'à maintenant s'il est encore ouvert). */
    public function getDurationInSeconds(?\DateTimeImmutable $now = null): int
    {
        $end = $this->resolvedAt ?? $now ?? new \DateTimeImmutable();

        return max(0, $end->getTimestamp() - $this->startedAt->getTimestamp());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, MonitoredService> */
    public function getAffectedServices(): Collection
    {
        return $this->affectedServices;
    }

    public function affects(MonitoredService $service): bool
    {
        return $this->affectedServices->contains($service);
    }

    /** RG-INC-01 : uniquement des services du même projet. */
    public function addAffectedService(MonitoredService $service): static
    {
        if ($service->getProject() !== $this->project) {
            throw new \InvalidArgumentException('Le service n\'appartient pas au projet de l\'incident.');
        }

        if (!$this->affectedServices->contains($service)) {
            $this->affectedServices->add($service);
        }

        return $this;
    }

    public function removeAffectedService(MonitoredService $service): static
    {
        $this->affectedServices->removeElement($service);

        return $this;
    }

    /** @return Collection<int, IncidentUpdate> */
    public function getUpdates(): Collection
    {
        return $this->updates;
    }

    /**
     * Ajoute une entrée à la timeline et fait évoluer le statut de l'incident (RG-INC-02/03/04).
     *
     * @throws IncidentAlreadyResolvedException
     */
    public function addUpdate(
        IncidentStatus $status,
        string $message,
        ?User $author = null,
        ?\DateTimeImmutable $at = null,
    ): IncidentUpdate {
        $this->assertNotResolved();

        $at ??= new \DateTimeImmutable();
        $update = new IncidentUpdate($this, $status, $message, $author, $at);
        $this->updates->add($update);
        $this->status = $status;

        if (IncidentStatus::Resolved === $status) {
            $this->resolvedAt = $at < $this->startedAt ? $this->startedAt : $at;
        }

        return $update;
    }

    public function resolve(string $message, ?User $author = null, ?\DateTimeImmutable $at = null): IncidentUpdate
    {
        return $this->addUpdate(IncidentStatus::Resolved, $message, $author, $at);
    }

    #[Assert\Callback]
    public function validateServicesBelongToProject(ExecutionContextInterface $context): void
    {
        foreach ($this->affectedServices as $service) {
            if ($service->getProject() !== $this->project) {
                $context->buildViolation('Le service « {{ name }} » n\'appartient pas à ce projet.')
                    ->setParameter('{{ name }}', $service->getName())
                    ->atPath('affectedServices')
                    ->addViolation();
            }
        }
    }

    private function assertNotResolved(): void
    {
        if ($this->isResolved()) {
            throw IncidentAlreadyResolvedException::forIncident($this->id);
        }
    }
}
