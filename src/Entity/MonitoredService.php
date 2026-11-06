<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CheckInterval;
use App\Enum\HttpMethod;
use App\Enum\ServiceStatus;
use App\Repository\MonitoredServiceRepository;
use App\Validator\PublicUrl;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Endpoint HTTP surveillé. Porte aussi l'état courant du service (statut, compteurs),
 * dénormalisé pour éviter de recalculer à partir de l'historique des checks.
 */
#[ORM\Entity(repositoryClass: MonitoredServiceRepository::class)]
#[ORM\Table(name: 'monitored_service')]
#[ORM\Index(name: 'idx_service_due', columns: ['is_active', 'next_check_at'])]
class MonitoredService
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2048)]
    #[PublicUrl]
    private string $url;

    #[ORM\Column(length: 4, enumType: HttpMethod::class)]
    private HttpMethod $httpMethod = HttpMethod::Get;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 100, max: 599)]
    private int $expectedStatusCode = 200;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 30)]
    private int $timeoutSeconds = 10;

    #[ORM\Column(name: 'check_interval_seconds', type: Types::INTEGER, enumType: CheckInterval::class)]
    private CheckInterval $checkInterval = CheckInterval::FiveMinutes;

    #[ORM\Column]
    #[Assert\Range(min: 100, max: 30000)]
    private int $degradedThresholdMs = 2000;

    /** Nombre d'échecs consécutifs avant de déclarer le service "down" (RG-STATUS-02). */
    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 10)]
    private int $failureThreshold = 3;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isPublic = true;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\PositiveOrZero]
    private int $position = 0;

    // ---- État courant -------------------------------------------------------

    #[ORM\Column(length: 20, enumType: ServiceStatus::class)]
    private ServiceStatus $currentStatus = ServiceStatus::Unknown;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $consecutiveFailures = 0;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $consecutiveSuccesses = 0;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastCheckedAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextCheckAt = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Project $project, string $name, string $url)
    {
        $this->project = $project;
        $this->name = $name;
        $this->url = $url;
        $this->createdAt = new \DateTimeImmutable();
        $project->addService($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getHttpMethod(): HttpMethod
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(HttpMethod $httpMethod): static
    {
        $this->httpMethod = $httpMethod;

        return $this;
    }

    public function getExpectedStatusCode(): int
    {
        return $this->expectedStatusCode;
    }

    public function setExpectedStatusCode(int $expectedStatusCode): static
    {
        $this->expectedStatusCode = $expectedStatusCode;

        return $this;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function setTimeoutSeconds(int $timeoutSeconds): static
    {
        $this->timeoutSeconds = $timeoutSeconds;

        return $this;
    }

    public function getCheckInterval(): CheckInterval
    {
        return $this->checkInterval;
    }

    public function setCheckInterval(CheckInterval $checkInterval): static
    {
        $this->checkInterval = $checkInterval;

        return $this;
    }

    public function getDegradedThresholdMs(): int
    {
        return $this->degradedThresholdMs;
    }

    public function setDegradedThresholdMs(int $degradedThresholdMs): static
    {
        $this->degradedThresholdMs = $degradedThresholdMs;

        return $this;
    }

    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    public function setFailureThreshold(int $failureThreshold): static
    {
        $this->failureThreshold = $failureThreshold;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /** RG-STATUS-06 : la pause fige le service. */
    public function pause(): void
    {
        $this->isActive = false;
        $this->currentStatus = ServiceStatus::Paused;
        $this->nextCheckAt = null;
        $this->consecutiveFailures = 0;
        $this->consecutiveSuccesses = 0;
    }

    public function resume(): void
    {
        $this->isActive = true;
        $this->currentStatus = ServiceStatus::Unknown;
        $this->nextCheckAt = null; // vérifié au prochain passage du scheduler
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getCurrentStatus(): ServiceStatus
    {
        return $this->currentStatus;
    }

    /** Réservé au moteur de monitoring (ServiceStatusResolver). */
    public function setCurrentStatus(ServiceStatus $currentStatus): void
    {
        $this->currentStatus = $currentStatus;
    }

    public function getConsecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    public function getConsecutiveSuccesses(): int
    {
        return $this->consecutiveSuccesses;
    }

    /** RG-STATUS-01 + RG-CHECK-02 : mise à jour des compteurs et planification du prochain check. */
    public function recordCheck(bool $isUp, \DateTimeImmutable $checkedAt): void
    {
        if ($isUp) {
            $this->consecutiveFailures = 0;
            ++$this->consecutiveSuccesses;
        } else {
            ++$this->consecutiveFailures;
            $this->consecutiveSuccesses = 0;
        }

        $this->lastCheckedAt = $checkedAt;
        $this->nextCheckAt = $checkedAt->modify(\sprintf('+%d seconds', $this->checkInterval->value));
    }

    public function getLastCheckedAt(): ?\DateTimeImmutable
    {
        return $this->lastCheckedAt;
    }

    public function getNextCheckAt(): ?\DateTimeImmutable
    {
        return $this->nextCheckAt;
    }

    public function isDueForCheck(\DateTimeImmutable $now): bool
    {
        return $this->isActive && (null === $this->nextCheckAt || $this->nextCheckAt <= $now);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
