<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<MonitoredService>
 */
class MonitoredServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonitoredService::class);
    }

    /**
     * Identifiants des services à vérifier maintenant (RG-CHECK-01).
     * On ne renvoie que les IDs : chaque check est ensuite traité dans son propre message.
     *
     * @return list<int>
     */
    public function findIdsDueForCheck(\DateTimeImmutable $now, int $limit = 500): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.id')
            ->andWhere('s.isActive = true')
            ->andWhere('s.nextCheckAt IS NULL OR s.nextCheckAt <= :now')
            ->setParameter('now', $now)
            ->orderBy('s.nextCheckAt', SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $rows);
    }

    public function countByProject(Project $project): int
    {
        return $this->count(['project' => $project]);
    }

    public function countByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.project', 'p')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
