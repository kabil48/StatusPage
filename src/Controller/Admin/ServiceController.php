<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractAppController;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Form\MonitoredServiceType;
use App\Repository\MonitorCheckRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\Quota\QuotaChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceController extends AbstractAppController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuotaChecker $quotaChecker,
    ) {
    }

    #[Route('/dashboard/projects/{id}/services/new', name: 'app_service_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(Request $request, Project $project): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::EDIT, $project);

        if (!$this->quotaChecker->canAddService($project)) {
            $this->addFlash('warning', \sprintf('Limite de %d services par projet atteinte.', $this->quotaChecker->getMaxServicesPerProject()));

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $service = new MonitoredService($project, '', '');
        $service->setPosition($project->getServices()->count());

        $form = $this->createForm(MonitoredServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($service);
            $this->entityManager->flush();

            $this->addFlash('success', \sprintf('Service « %s » ajouté. Il sera vérifié lors du prochain passage du moniteur.', $service->getName()));

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        // Le service a été ajouté à la collection du projet par son constructeur : on l'en retire en cas d'échec.
        $project->getServices()->removeElement($service);

        return $this->render('admin/service/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/dashboard/services/{id}', name: 'app_service_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(MonitoredService $service, MonitorCheckRepository $checkRepository): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::VIEW, $service);

        return $this->render('admin/service/show.html.twig', [
            'service' => $service,
            'project' => $service->getProject(),
            'latest_checks' => $checkRepository->findLatestForService($service, 20),
        ]);
    }

    #[Route('/dashboard/services/{id}/edit', name: 'app_service_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, MonitoredService $service): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::EDIT, $service);

        $form = $this->createForm(MonitoredServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Service mis à jour.');

            return $this->redirectToRoute('app_service_show', ['id' => $service->getId()]);
        }

        return $this->render('admin/service/edit.html.twig', [
            'service' => $service,
            'project' => $service->getProject(),
            'form' => $form,
        ]);
    }

    #[Route('/dashboard/services/{id}/toggle', name: 'app_service_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, MonitoredService $service): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::EDIT, $service);

        if ($this->isCsrfTokenValid('toggle-service-'.$service->getId(), $request->getPayload()->getString('_token'))) {
            if ($service->isActive()) {
                $service->pause();
                $this->addFlash('info', 'Surveillance mise en pause.');
            } else {
                $service->resume();
                $this->addFlash('success', 'Surveillance reprise.');
            }
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('app_service_show', ['id' => $service->getId()]);
    }

    #[Route('/dashboard/services/{id}/delete', name: 'app_service_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, MonitoredService $service): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::DELETE, $service);
        $projectId = $service->getProject()->getId();

        if ($this->isCsrfTokenValid('delete-service-'.$service->getId(), $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($service);
            $this->entityManager->flush();
            $this->addFlash('success', 'Service supprimé.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $projectId]);
    }
}
