<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractAppController;
use App\Dto\IncidentUpdateInput;
use App\Dto\NewIncidentInput;
use App\Entity\Incident;
use App\Entity\Project;
use App\Enum\IncidentStatus;
use App\Exception\IncidentAlreadyResolvedException;
use App\Form\IncidentDetailsType;
use App\Form\IncidentUpdateType;
use App\Form\NewIncidentType;
use App\Repository\IncidentRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\Incident\IncidentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/** Incidents manuels d'un projet : déclaration, timeline, résolution (F6). */
#[Route('/dashboard/projects/{projectId}/incidents', requirements: ['projectId' => '\d+'])]
final class IncidentController extends AbstractAppController
{
    private const PER_PAGE = 20;

    public function __construct(
        private readonly IncidentManager $incidentManager,
        private readonly IncidentRepository $incidentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_incident_index', methods: ['GET'])]
    public function index(
        #[MapEntity(id: 'projectId')] Project $project,
        #[MapQueryParameter] int $page = 1,
    ): Response {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::VIEW, $project);

        $paginator = $this->incidentRepository->paginateByProject($project, $page, self::PER_PAGE);

        return $this->render('admin/incident/index.html.twig', [
            'project' => $project,
            'incidents' => $paginator,
            'page' => max(1, $page),
            'pages' => max(1, (int) ceil(\count($paginator) / self::PER_PAGE)),
        ]);
    }

    #[Route('/new', name: 'app_incident_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[MapEntity(id: 'projectId')] Project $project): Response
    {
        $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::EDIT, $project);

        if ($project->getServices()->isEmpty()) {
            $this->addFlash('warning', 'Ajoutez d\'abord un service : un incident affecte au moins un service.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $input = new NewIncidentInput();
        $form = $this->createForm(NewIncidentType::class, $input, ['project' => $project]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $incident = $this->incidentManager->open(
                $project,
                $input->title,
                $input->impact,
                $input->affectedServices,
                $input->status,
                $input->message,
                $this->getAppUser(),
            );
            $this->addFlash('success', 'Incident déclaré. Il est visible sur la page publique.');

            return $this->redirectToIncident($incident);
        }

        return $this->render('admin/incident/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_incident_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, int $projectId, Incident $incident): Response
    {
        $this->assertIncidentAccess($incident, $projectId, ProjectVoter::VIEW);

        $input = new IncidentUpdateInput($incident->getStatus());
        $form = $this->createForm(IncidentUpdateType::class, $input, [
            'action' => $this->generateUrl('app_incident_show', ['projectId' => $projectId, 'id' => $incident->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->denyAccessUnlessGrantedOrNotFound(ProjectVoter::EDIT, $incident);

            if ($incident->isResolved()) {
                return $this->rejectResolved($incident);
            }

            if ($form->isValid() && null !== $input->status) {
                $update = $this->incidentManager->addUpdate($incident, $input->status, $input->message, $this->getAppUser());
                $this->addFlash('success', IncidentStatus::Resolved === $update->getStatus() ? 'Incident résolu.' : 'Mise à jour publiée.');

                return $this->redirectToIncident($incident);
            }
        }

        return $this->render('admin/incident/show.html.twig', [
            'project' => $incident->getProject(),
            'incident' => $incident,
            'update_form' => $incident->isOpen() && $this->isGranted(ProjectVoter::EDIT, $incident) ? $form : null,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/edit', name: 'app_incident_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $projectId, Incident $incident): Response
    {
        $this->assertIncidentAccess($incident, $projectId, ProjectVoter::EDIT);

        if ($incident->isResolved()) {
            return $this->rejectResolved($incident);
        }

        $form = $this->createForm(IncidentDetailsType::class, $incident, ['project' => $incident->getProject()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Incident modifié.');

            return $this->redirectToIncident($incident);
        }

        return $this->render('admin/incident/edit.html.twig', [
            'project' => $incident->getProject(),
            'incident' => $incident,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_incident_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $projectId, Incident $incident): Response
    {
        $this->assertIncidentAccess($incident, $projectId, ProjectVoter::DELETE);

        if ($this->isCsrfTokenValid('delete-incident-'.$incident->getId(), $request->getPayload()->getString('_token'))) {
            $this->incidentManager->delete($incident);
            $this->addFlash('success', 'Incident supprimé.');
        }

        return $this->redirectToRoute('app_incident_index', ['projectId' => $projectId]);
    }

    /** L'incident doit appartenir au projet de l'URL, sinon 404 (RG-SEC-01). */
    private function assertIncidentAccess(Incident $incident, int $projectId, string $attribute): void
    {
        if ($incident->getProject()->getId() !== $projectId) {
            throw $this->createNotFoundException('Incident introuvable.');
        }
        $this->denyAccessUnlessGrantedOrNotFound($attribute, $incident);
    }

    /** RG-INC-02 : un incident résolu est figé. */
    private function rejectResolved(Incident $incident): Response
    {
        $this->addFlash('warning', IncidentAlreadyResolvedException::forIncident($incident->getId())->getMessage());

        return $this->redirectToIncident($incident);
    }

    private function redirectToIncident(Incident $incident): Response
    {
        return $this->redirectToRoute('app_incident_show', [
            'projectId' => $incident->getProject()->getId(),
            'id' => $incident->getId(),
        ]);
    }
}
