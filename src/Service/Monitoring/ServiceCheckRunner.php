<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Entity\MonitorCheck;
use App\Event\ServiceStatusChangedEvent;
use App\Repository\MonitoredServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Orchestration d'un check : verrou → requête HTTP → persistance → calcul du statut → événement.
 * Code métier commun aux deux modes d'exécution (worker Messenger et déclencheur HTTP, §5.3).
 */
final class ServiceCheckRunner
{
    private const LOCK_TTL_SECONDS = 60;

    public function __construct(
        private readonly MonitoredServiceRepository $serviceRepository,
        private readonly HttpChecker $httpChecker,
        private readonly ServiceStatusResolver $statusResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LockFactory $lockFactory,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Vérifie un service. Retourne null si le check n'a pas été exécuté
     * (service supprimé, en pause, pas encore dû, ou déjà en cours ailleurs).
     *
     * @param bool $force ignore la date du prochain check (check immédiat demandé par l'utilisateur)
     */
    public function run(int $serviceId, bool $force = false): ?CheckResult
    {
        $service = $this->serviceRepository->find($serviceId);
        if (null === $service || !$service->isActive()) {
            return null;
        }

        // RG-CHECK-07 : jamais deux checks simultanés sur le même service.
        $lock = $this->lockFactory->createLock('monitoring-service-'.$serviceId, self::LOCK_TTL_SECONDS);
        if (!$lock->acquire()) {
            return null;
        }

        try {
            // RG-CHECK-01 : re-vérifié sous verrou (un message en double peut arriver après un check déjà fait).
            if (!$force && !$service->isDueForCheck($this->clock->now())) {
                return null;
            }

            $result = $this->httpChecker->check($service);

            $this->entityManager->persist(new MonitorCheck(
                $service,
                $result->checkedAt,
                $result->isUp,
                $result->statusCode,
                $result->responseTimeMs,
                $result->errorMessage,
            ));

            $previous = $service->getCurrentStatus();
            $current = $this->statusResolver->resolve($service, $result);

            if ($previous !== $current) {
                $this->logger->info('Service {service} : {from} → {to}', [
                    'service' => $serviceId,
                    'from' => $previous->value,
                    'to' => $current->value,
                ]);
                $this->eventDispatcher->dispatch(new ServiceStatusChangedEvent(
                    $service,
                    $previous,
                    $current,
                    $result->checkedAt,
                    $result->errorMessage,
                ));
            }

            $this->entityManager->flush();

            return $result;
        } finally {
            $lock->release();
        }
    }

    /**
     * Exécute de façon synchrone tous les checks dus (commande / déclencheur HTTP).
     *
     * @return int nombre de checks effectivement exécutés
     */
    public function runDueChecks(int $limit = 100): int
    {
        $ids = $this->serviceRepository->findIdsDueForCheck($this->clock->now(), $limit);
        $executed = 0;

        foreach ($ids as $id) {
            try {
                if (null !== $this->run($id)) {
                    ++$executed;
                }
            } catch (\Throwable $e) {
                // Un service en erreur ne doit pas bloquer les suivants.
                $this->logger->error('Échec du check du service {service} : {error}', ['service' => $id, 'error' => $e->getMessage(), 'exception' => $e]);
            } finally {
                $this->entityManager->clear(); // mémoire bornée sur de gros volumes
            }
        }

        return $executed;
    }
}
