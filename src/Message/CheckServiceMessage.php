<?php

declare(strict_types=1);

namespace App\Message;

/** Demande de vérification d'un service (consommée par le worker, transport `async`). */
final readonly class CheckServiceMessage
{
    public function __construct(
        public int $serviceId,
        public bool $force = false,
    ) {
    }
}
