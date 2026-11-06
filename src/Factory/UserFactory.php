<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * L'attribut "password" reçoit un mot de passe en clair, hashé après instanciation.
 *
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const DEFAULT_PASSWORD = 'password123';

    public function __construct(private readonly ?UserPasswordHasherInterface $passwordHasher = null)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    public function admin(): self
    {
        return $this->with(['roles' => [User::ROLE_ADMIN]]);
    }

    public function inactive(): self
    {
        return $this->with(['isActive' => false]);
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'displayName' => self::faker()->name(),
            'password' => self::DEFAULT_PASSWORD,
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            if (null !== $this->passwordHasher) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $user->getPassword()));
            }
        });
    }
}
