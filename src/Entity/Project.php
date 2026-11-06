<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SortDirection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un projet = une page de statut publique.
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Index(name: 'idx_project_owner', columns: ['owner_id'])]
#[UniqueEntity(fields: ['slug'], message: 'Cette adresse de page de statut est déjà utilisée.')]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 100)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Uniquement des minuscules, chiffres et tirets.')]
    private string $slug;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isPublic = true;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, MonitoredService> */
    #[ORM\OneToMany(targetEntity: MonitoredService::class, mappedBy: 'project', orphanRemoval: true)]
    #[ORM\OrderBy(['position' => SortDirection::Ascending, 'id' => SortDirection::Ascending])]
    private Collection $services;

    /** @var Collection<int, Incident> */
    #[ORM\OneToMany(targetEntity: Incident::class, mappedBy: 'project', orphanRemoval: true)]
    #[ORM\OrderBy(['startedAt' => SortDirection::Descending])]
    private Collection $incidents;

    public function __construct(User $owner, string $name, string $slug)
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->slug = $slug;
        $this->createdAt = new \DateTimeImmutable();
        $this->services = new ArrayCollection();
        $this->incidents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner === $user
            || (null !== $user->getId() && $this->owner->getId() === $user->getId());
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, MonitoredService> */
    public function getServices(): Collection
    {
        return $this->services;
    }

    /**
     * Services visibles sur la page publique (RG-SEC-06).
     *
     * @return list<MonitoredService>
     */
    public function getPublicServices(): array
    {
        return array_values($this->services->filter(static fn (MonitoredService $s) => $s->isPublic())->toArray());
    }

    public function addService(MonitoredService $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
        }

        return $this;
    }

    /** @return Collection<int, Incident> */
    public function getIncidents(): Collection
    {
        return $this->incidents;
    }

    public function addIncident(Incident $incident): static
    {
        if (!$this->incidents->contains($incident)) {
            $this->incidents->add($incident);
        }

        return $this;
    }
}
