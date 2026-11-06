<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractAppController extends AbstractController
{
    /**
     * RG-SEC-01 : en cas de refus on répond 404 (et non 403) pour ne pas
     * révéler l'existence d'une ressource appartenant à un autre utilisateur.
     */
    protected function denyAccessUnlessGrantedOrNotFound(string $attribute, mixed $subject): void
    {
        if (!$this->isGranted($attribute, $subject)) {
            throw new NotFoundHttpException('Ressource introuvable.');
        }
    }

    protected function getAppUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
