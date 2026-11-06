<?php

declare(strict_types=1);

namespace App\Service\Monitoring;

use App\Entity\MonitoredService;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Exécute une requête HTTP vers un service surveillé (RG-CHECK-03 à 06).
 * Le client injecté est enveloppé dans NoPrivateNetworkHttpClient (anti-SSRF à l'exécution).
 */
final class HttpChecker
{
    public const USER_AGENT = 'KabivoxBot/1.0';
    private const MAX_REDIRECTS = 3;

    public function __construct(
        #[Autowire(service: 'monitoring.http_client')]
        private readonly HttpClientInterface $httpClient,
        private readonly ClockInterface $clock,
    ) {
    }

    public function check(MonitoredService $service): CheckResult
    {
        $checkedAt = $this->clock->now();
        $timeout = (float) $service->getTimeoutSeconds();
        $start = hrtime(true);

        try {
            $response = $this->httpClient->request($service->getHttpMethod()->value, $service->getUrl(), [
                'timeout' => $timeout,
                'max_duration' => $timeout,
                'max_redirects' => self::MAX_REDIRECTS,
                'headers' => ['User-Agent' => self::USER_AGENT],
            ]);

            $statusCode = $response->getStatusCode();
            $response->getContent(false); // temps total = corps inclus (RG-CHECK-04)
        } catch (TransportExceptionInterface $e) {
            return new CheckResult(false, $checkedAt, null, $this->elapsedMs($start), '' !== $e->getMessage() ? $e->getMessage() : 'Erreur de connexion');
        }

        $responseTimeMs = $this->elapsedMs($start);
        $expected = $service->getExpectedStatusCode();

        if ($statusCode !== $expected) {
            return new CheckResult(false, $checkedAt, $statusCode, $responseTimeMs, \sprintf('Code HTTP %d reçu, %d attendu', $statusCode, $expected));
        }

        return new CheckResult(true, $checkedAt, $statusCode, $responseTimeMs);
    }

    private function elapsedMs(int|float $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
