<?php

declare(strict_types=1);

namespace App\Service\Quota;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\MonitoredServiceRepository;
use App\Repository\ProjectRepository;

/** RG-SEC-07 : limites anti-abus (configurables dans services.yaml). */
final class QuotaChecker
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly MonitoredServiceRepository $serviceRepository,
        private readonly int $maxProjectsPerUser,
        private readonly int $maxServicesPerProject,
    ) {
    }

    public function canCreateProject(User $user): bool
    {
        return $user->isAdmin() || $this->projectRepository->countByOwner($user) < $this->maxProjectsPerUser;
    }

    public function canAddService(Project $project): bool
    {
        return $project->getOwner()->isAdmin()
            || $this->serviceRepository->countByProject($project) < $this->maxServicesPerProject;
    }

    public function getMaxProjectsPerUser(): int
    {
        return $this->maxProjectsPerUser;
    }

    public function getMaxServicesPerProject(): int
    {
        return $this->maxServicesPerProject;
    }
}
