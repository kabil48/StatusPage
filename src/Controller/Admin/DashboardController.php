<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractAppController;
use App\Enum\ServiceStatus;
use App\Repository\ProjectRepository;
use App\Service\Status\ProjectStatusResolver;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractAppController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository, ProjectStatusResolver $statusResolver): Response
    {
        $projects = $projectRepository->findByOwner($this->getAppUser());

        $counters = ['services' => 0, 'down' => 0, 'degraded' => 0, 'open_incidents' => 0];
        $projectStatuses = [];

        foreach ($projects as $project) {
            $projectStatuses[$project->getId()] = $statusResolver->resolve($project->getServices());

            foreach ($project->getServices() as $service) {
                ++$counters['services'];
                match ($service->getCurrentStatus()) {
                    ServiceStatus::Down => ++$counters['down'],
                    ServiceStatus::Degraded => ++$counters['degraded'],
                    default => null,
                };
            }

            foreach ($project->getIncidents() as $incident) {
                if ($incident->isOpen()) {
                    ++$counters['open_incidents'];
                }
            }
        }

        return $this->render('admin/dashboard/index.html.twig', [
            'projects' => $projects,
            'project_statuses' => $projectStatuses,
            'counters' => $counters,
        ]);
    }
}
