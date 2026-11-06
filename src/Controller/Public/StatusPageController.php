<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Project;
use App\Repository\IncidentRepository;
use App\Repository\ProjectRepository;
use App\Service\Status\ProjectStatusResolver;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page de statut publique (version initiale — les barres d'uptime 90 jours arrivent en semaine 3).
 */
#[Route('/status/{slug}', requirements: ['slug' => '[a-z0-9-]+'])]
final class StatusPageController extends AbstractController
{
    private const RECENT_DAYS = 14;
    private const PER_PAGE = 20;

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly IncidentRepository $incidentRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_status_page', methods: ['GET'])]
    public function show(string $slug, ProjectStatusResolver $statusResolver): Response
    {
        $project = $this->findPublicProject($slug);
        $services = $project->getPublicServices();

        return $this->render('public/status_page.html.twig', [
            'project' => $project,
            'services' => $services,
            'global_status' => $statusResolver->resolve($services),
            'open_incidents' => $this->incidentRepository->findOpenByProject($project),
            'recent_incidents' => $this->incidentRepository->findResolvedSince(
                $project,
                $this->clock->now()->modify(\sprintf('-%d days', self::RECENT_DAYS)),
            ),
        ]);
    }

    /** RG-INC-05 : historique complet paginé. */
    #[Route('/incidents', name: 'app_status_incidents', methods: ['GET'])]
    public function incidents(string $slug, #[MapQueryParameter] int $page = 1): Response
    {
        $project = $this->findPublicProject($slug);
        $paginator = $this->incidentRepository->paginateByProject($project, $page, self::PER_PAGE);

        return $this->render('public/incident_history.html.twig', [
            'project' => $project,
            'incidents' => $paginator,
            'page' => max(1, $page),
            'pages' => max(1, (int) ceil(\count($paginator) / self::PER_PAGE)),
        ]);
    }

    #[Route('/incidents/{id}', name: 'app_status_incident', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function incident(string $slug, int $id): Response
    {
        $project = $this->findPublicProject($slug);
        $incident = $this->incidentRepository->findOneBy(['id' => $id, 'project' => $project])
            ?? throw $this->createNotFoundException('Incident introuvable.');

        return $this->render('public/incident_show.html.twig', [
            'project' => $project,
            'incident' => $incident,
        ]);
    }

    /** RG-SEC-06 : projet inexistant ou privé → 404. */
    private function findPublicProject(string $slug): Project
    {
        return $this->projectRepository->findPublicBySlug($slug)
            ?? throw $this->createNotFoundException('Page de statut introuvable.');
    }
}
