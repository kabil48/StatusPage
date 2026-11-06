<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CheckServiceMessage;
use App\Service\Monitoring\ServiceCheckRunner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckServiceHandler
{
    public function __construct(private readonly ServiceCheckRunner $runner)
    {
    }

    public function __invoke(CheckServiceMessage $message): void
    {
        $this->runner->run($message->serviceId, $message->force);
    }
}
