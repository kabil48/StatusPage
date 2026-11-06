<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\MonitoredService;
use App\Enum\CheckInterval;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<MonitoredService>
 */
final class MonitoredServiceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return MonitoredService::class;
    }

    protected function defaults(): array
    {
        return [
            'project' => ProjectFactory::new(),
            'name' => self::faker()->randomElement(['API', 'Site web', 'Back-office', 'Paiement', 'Authentification']).' '.self::faker()->numberBetween(1, 99),
            'url' => 'https://'.self::faker()->domainName().'/health',
            'checkInterval' => CheckInterval::FiveMinutes,
        ];
    }
}
