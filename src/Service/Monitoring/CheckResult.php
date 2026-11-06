<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Entity\MonitorCheck;

/** Résultat brut d'une vérification HTTP (value object, RG-CHECK-03/04). */
final readonly class CheckResult
{
    public ?string $errorMessage;

    public function __construct(
        public bool $isUp,
        public \DateTimeImmutable $checkedAt,
        public ?int $statusCode = null,
        public ?int $responseTimeMs = null,
        ?string $errorMessage = null,
    ) {
        $this->errorMessage = null === $errorMessage ? null : mb_substr($errorMessage, 0, MonitorCheck::ERROR_MAX_LENGTH);
    }
}
