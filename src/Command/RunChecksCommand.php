<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Monitoring\ServiceCheckRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:checks:run', description: 'Exécute de façon synchrone les checks dus (sans worker).')]
final class RunChecksCommand
{
    public function __construct(private readonly ServiceCheckRunner $runner)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Nombre maximum de services à vérifier')] int $limit = 100): int
    {
        $count = $this->runner->runDueChecks($limit);
        $io->success(\sprintf('%d check(s) exécuté(s).', $count));

        return Command::SUCCESS;
    }
}
