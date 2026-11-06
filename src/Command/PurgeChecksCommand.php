<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\MonitorCheckRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:checks:purge', description: 'Supprime les checks plus anciens que la durée de rétention.')]
final class PurgeChecksCommand
{
    public function __construct(
        private readonly MonitorCheckRepository $checkRepository,
        private readonly ClockInterface $clock,
        #[Autowire(param: 'app.check_retention_days')]
        private readonly int $retentionDays,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $before = $this->clock->now()->modify(\sprintf('-%d days', $this->retentionDays));
        $deleted = $this->checkRepository->deleteOlderThan($before);
        $io->success(\sprintf('%d check(s) antérieur(s) au %s supprimé(s).', $deleted, $before->format('Y-m-d H:i')));

        return Command::SUCCESS;
    }
}
