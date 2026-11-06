<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Project;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Project>
 */
final class ProjectFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Project::class;
    }

    protected function defaults(): array
    {
        return [
            'owner' => UserFactory::new(),
            'name' => self::faker()->company(),
            'slug' => self::faker()->unique()->slug(2),
            'description' => self::faker()->sentence(),
            'isPublic' => true,
        ];
    }
}
