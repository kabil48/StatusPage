<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\DispatchDueChecksMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/** Consommé par `messenger:consume scheduler_monitoring` (mode worker, §5.3). */
#[AsSchedule('monitoring')]
final class MonitoringSchedule implements ScheduleProviderInterface
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('1 minute', new DispatchDueChecksMessage()))
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
