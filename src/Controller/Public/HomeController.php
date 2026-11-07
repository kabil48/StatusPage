<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /** Projet de démonstration créé par les fixtures (AppFixtures). */
    private const DEMO_SLUG = 'acme-cloud';

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        ProjectRepository $projects,
        #[Autowire('%app.max_projects_per_user%')] int $maxProjects,
        #[Autowire('%app.max_services_per_project%')] int $maxServices,
    ): Response {
        // Le lien « Voir la démo » n'apparaît que si le projet de démo existe et est public.
        $demo = $projects->findOneBy(['slug' => self::DEMO_SLUG, 'isPublic' => true]);

        return $this->render('public/home.html.twig', [
            'demo_slug' => null !== $demo ? self::DEMO_SLUG : null,
            // Quotas réellement appliqués (QuotaChecker), affichés dans la grille tarifaire.
            'max_projects' => $maxProjects,
            'max_services' => $maxServices,
        ]);
    }
}
