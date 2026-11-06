<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\ServiceStatusChangedEvent;
use App\Service\Incident\IncidentAutoManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Relie le moteur de monitoring au module incidents sans couplage direct (RG-INC-AUTO-04). */
final class IncidentAutoSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly IncidentAutoManager $incidentAutoManager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [ServiceStatusChangedEvent::class => 'onServiceStatusChanged'];
    }

    public function onServiceStatusChanged(ServiceStatusChangedEvent $event): void
    {
        $this->incidentAutoManager->handle($event);
    }
}
