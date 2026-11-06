<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MonitorCheck;
use App\Entity\MonitoredService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<MonitorCheck>
 */
class MonitorCheckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonitorCheck::class);
    }

    /** @return list<MonitorCheck> */
    public function findLatestForService(MonitoredService $service, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.service = :service')
            ->setParameter('service', $service)
            ->orderBy('c.checkedAt', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Purge des checks plus anciens que la date donnée (rétention, §3). Retourne le nombre de lignes supprimées. */
    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.checkedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
