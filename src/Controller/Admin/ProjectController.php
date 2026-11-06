<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractAppController;
use App\Entity\Project;
use App\Form\ProjectType;
use App\Security\Voter\ProjectVoter;
use App\Service\Quota\QuotaChecker;
use App\Service\Status\ProjectStatusResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/projects')]
final class ProjectController extends AbstractAppController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuotaChecker $quotaChecker,
    ) {
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->getAppUser();

        if (!$this->quotaChecker->canCreateProject($user)) {
            $this->addFlash('warning', \sprintf('Vous avez atteint la limite de %d projets.', $this->quotaChecker->getMaxProjectsPerUser()));

            return $this->redirectToRoute('app_dashboard');
        }

        $project = new Project($user, '', '');
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($project);
            $this->entityManager->flush();

            $this->addFlash('success', 'Projet créé. Ajoutez maintenant les services à surveiller.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('admin/project/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_project_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Project $project, ProjectStatusResolver $statusResolver): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::VIEW, $project);

        return $this->render('admin/project/show.html.twig', [
            'project' => $project,
            'global_status' => $statusResolver->resolve($project->getServices()),
            'can_add_service' => $this->quotaChecker->canAddService($project),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::EDIT, $project);

        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Projet mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('admin/project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_project_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Project $project): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::DELETE, $project);

        if ($this->isCsrfTokenValid('delete-project-'.$project->getId(), $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($project);
            $this->entityManager->flush();
            $this->addFlash('success', 'Projet supprimé.');
        }

        return $this->redirectToRoute('app_dashboard');
    }
}
