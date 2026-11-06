<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CheckServiceMessage;
use App\Message\DispatchDueChecksMessage;
use App\Repository\MonitoredServiceRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/** Sélectionne les services dus (RG-CHECK-01) et publie un message par service. */
#[AsMessageHandler]
final class DispatchDueChecksHandler
{
    public function __construct(
        private readonly MonitoredServiceRepository $serviceRepository,
        private readonly MessageBusInterface $bus,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(DispatchDueChecksMessage $message): void
    {
        foreach ($this->serviceRepository->findIdsDueForCheck($this->clock->now()) as $id) {
            $this->bus->dispatch(new CheckServiceMessage($id));
        }
    }
}
