<?php

declare(strict_types=1);

namespace App\Controller\Internal;

use App\Service\Monitoring\ServiceCheckRunner;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Déclencheur HTTP du monitoring pour l'hébergement gratuit sans worker (§5.3).
 * Appelé par un cron externe toutes les 5 minutes avec l'en-tête X-Cron-Token.
 */
final class CronController
{
    public function __construct(
        private readonly ServiceCheckRunner $runner,
        #[Autowire(env: 'CRON_TOKEN')]
        private readonly string $cronToken,
    ) {
    }

    #[Route('/internal/cron/run-checks', name: 'internal_cron_run_checks', methods: ['POST'])]
    public function runChecks(Request $request): JsonResponse
    {
        $token = (string) $request->headers->get('X-Cron-Token', '');

        if ('' === $this->cronToken || '' === $token || !hash_equals($this->cronToken, $token)) {
            return new JsonResponse(['error' => 'Invalid cron token'], Response::HTTP_UNAUTHORIZED);
        }

        set_time_limit(0);

        return new JsonResponse(['executed' => $this->runner->runDueChecks()]);
    }
}
