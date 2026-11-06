<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Incident;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RG-SEC-01 : un utilisateur n'accède qu'aux ressources de ses propres projets.
 *
 * Le sujet peut être un Project, ou une ressource rattachée à un projet (service, incident) :
 * les droits sont toujours évalués au niveau du projet propriétaire.
 *
 * @extends Voter<string, Project|MonitoredService|Incident>
 */
final class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';

    private const ATTRIBUTES = [self::VIEW, self::EDIT, self::DELETE];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true)
            && ($subject instanceof Project || $subject instanceof MonitoredService || $subject instanceof Incident);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $project = $subject instanceof Project ? $subject : $subject->getProject();

        // Les administrateurs de la plateforme peuvent consulter, mais pas modifier, les projets des autres.
        if (self::VIEW === $attribute && $user->isAdmin()) {
            return true;
        }

        return $project->isOwnedBy($user);
    }
}
