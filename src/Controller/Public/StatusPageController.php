<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\IncidentRepository;
use App\Repository\ProjectRepository;
use App\Service\Status\ProjectStatusResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page de statut publique (version initiale — les barres d'uptime 90 jours arrivent en semaine 3).
 */
final class StatusPageController extends AbstractController
{
    #[Route('/status/{slug}', name: 'app_status_page', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(
        string $slug,
        ProjectRepository $projectRepository,
        IncidentRepository $incidentRepository,
        ProjectStatusResolver $statusResolver,
    ): Response {
        // RG-SEC-06 : projet inexistant ou privé → 404
        $project = $projectRepository->findPublicBySlug($slug)
            ?? throw $this->createNotFoundException('Page de statut introuvable.');

        $services = $project->getPublicServices();

        return $this->render('public/status_page.html.twig', [
            'project' => $project,
            'services' => $services,
            'global_status' => $statusResolver->resolve($services),
            'open_incidents' => $incidentRepository->findOpenByProject($project),
            'recent_incidents' => $incidentRepository->findResolvedSince($project, new \DateTimeImmutable('-14 days')),
        ]);
    }
}
