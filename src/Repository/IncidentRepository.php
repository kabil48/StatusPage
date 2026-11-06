<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Incident;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Enum\IncidentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Incident>
 */
class IncidentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Incident::class);
    }

    /** @return list<Incident> */
    public function findOpenByProject(Project $project): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.status != :resolved')
            ->setParameter('project', $project)
            ->setParameter('resolved', IncidentStatus::Resolved)
            ->orderBy('i.startedAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique paginé des incidents d'un projet, du plus récent au plus ancien (RG-INC-05).
     *
     * @return Paginator<Incident>
     */
    public function paginateByProject(Project $project, int $page, int $limit = 20, ?bool $open = null): Paginator
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->setParameter('project', $project)
            ->orderBy('i.startedAt', \SortDirection::Descending)
            ->addOrderBy('i.id', \SortDirection::Descending)
            ->setFirstResult((max(1, $page) - 1) * $limit)
            ->setMaxResults($limit);

        if (null !== $open) {
            $qb->andWhere($open ? 'i.status != :resolved' : 'i.status = :resolved')
                ->setParameter('resolved', IncidentStatus::Resolved);
        }

        return new Paginator($qb->getQuery(), fetchJoinCollection: false);
    }

    /** Incident automatique encore ouvert pour ce service (RG-INC-AUTO-01 : pas de doublon). */
    public function findOpenAutoIncidentForService(MonitoredService $service): ?Incident
    {
        return $this->createQueryBuilder('i')
            ->join('i.affectedServices', 's')
            ->andWhere('s = :service')
            ->andWhere('i.isAutoCreated = true')
            ->andWhere('i.status != :resolved')
            ->setParameter('service', $service)
            ->setParameter('resolved', IncidentStatus::Resolved)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Incidents résolus depuis une date (RG-INC-05 : 14 derniers jours sur la page publique).
     *
     * @return list<Incident>
     */
    public function findResolvedSince(Project $project, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.project = :project')
            ->andWhere('i.status = :resolved')
            ->andWhere('i.resolvedAt >= :since')
            ->setParameter('project', $project)
            ->setParameter('resolved', IncidentStatus::Resolved)
            ->setParameter('since', $since)
            ->orderBy('i.startedAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();
    }
}
