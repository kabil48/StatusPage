<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MonitorCheckRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Résultat d'une vérification. Table à fort volume, en écriture seule (append-only) :
 * l'entité est immuable et la relation vers le service est unidirectionnelle.
 */
#[ORM\Entity(repositoryClass: MonitorCheckRepository::class, readOnly: true)]
#[ORM\Table(name: 'monitor_check')]
#[ORM\Index(name: 'idx_check_service_date', columns: ['service_id', 'checked_at'])]
class MonitorCheck
{
    public const ERROR_MAX_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private MonitoredService $service;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $checkedAt;

    #[ORM\Column]
    private bool $isUp;

    /** NULL si aucune réponse (timeout, DNS, TLS...). */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $statusCode;

    #[ORM\Column(nullable: true)]
    private ?int $responseTimeMs;

    #[ORM\Column(length: self::ERROR_MAX_LENGTH, nullable: true)]
    private ?string $errorMessage;

    public function __construct(
        MonitoredService $service,
        \DateTimeImmutable $checkedAt,
        bool $isUp,
        ?int $statusCode = null,
        ?int $responseTimeMs = null,
        ?string $errorMessage = null,
    ) {
        $this->service = $service;
        $this->checkedAt = $checkedAt;
        $this->isUp = $isUp;
        $this->statusCode = $statusCode;
        $this->responseTimeMs = $responseTimeMs;
        $this->errorMessage = null === $errorMessage ? null : mb_substr($errorMessage, 0, self::ERROR_MAX_LENGTH);
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getService(): MonitoredService
    {
        return $this->service;
    }

    public function getCheckedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function isUp(): bool
    {
        return $this->isUp;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponseTimeMs(): ?int
    {
        return $this->responseTimeMs;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
