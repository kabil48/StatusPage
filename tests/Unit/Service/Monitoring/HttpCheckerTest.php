<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Monitoring;

use App\Entity\MonitorCheck;
use App\Entity\MonitoredService;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\HttpMethod;
use App\Service\Monitoring\HttpChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpCheckerTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-06 10:00:00');
    }

    /** U-01 */
    public function testExpectedStatusCodeIsUp(): void
    {
        $response = new MockResponse('ok', ['http_code' => 200]);
        $result = $this->checker($response)->check($this->service());

        self::assertTrue($result->isUp);
        self::assertSame(200, $result->statusCode);
        self::assertNotNull($result->responseTimeMs);
        self::assertGreaterThanOrEqual(0, $result->responseTimeMs);
        self::assertNull($result->errorMessage);
        self::assertEquals($this->clock->now(), $result->checkedAt);

        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://example.com/health', $response->getRequestUrl());
        self::assertContains('User-Agent: StatusPageBot/1.0', $response->getRequestOptions()['headers']);
    }

    /** U-02 */
    public function testUnexpectedStatusCodeIsDown(): void
    {
        $result = $this->checker(new MockResponse('boom', ['http_code' => 500]))->check($this->service());

        self::assertFalse($result->isUp);
        self::assertSame(500, $result->statusCode);
        self::assertSame('Code HTTP 500 reçu, 200 attendu', $result->errorMessage);
    }

    /** U-03 */
    public function testTimeoutIsDownWithoutStatusCode(): void
    {
        $client = new MockHttpClient(static fn () => throw new TransportException('Idle timeout reached for "https://example.com/health".'));
        $result = (new HttpChecker($client, $this->clock))->check($this->service());

        self::assertFalse($result->isUp);
        self::assertNull($result->statusCode);
        self::assertStringContainsString('timeout', $result->errorMessage ?? '');
    }

    /** U-04 */
    public function testNetworkErrorIsDownAndMessageTruncated(): void
    {
        $longError = 'Could not resolve host: '.str_repeat('a', 400);
        $result = $this->checker(new MockResponse('', ['error' => $longError]))->check($this->service());

        self::assertFalse($result->isUp);
        self::assertNull($result->statusCode);
        self::assertSame(MonitorCheck::ERROR_MAX_LENGTH, mb_strlen($result->errorMessage ?? ''));
        self::assertStringStartsWith('Could not resolve host', $result->errorMessage ?? '');
    }

    public function testUsesConfiguredMethodAndExpectedCode(): void
    {
        $service = $this->service()->setHttpMethod(HttpMethod::Head)->setExpectedStatusCode(204);
        $response = new MockResponse('', ['http_code' => 204]);

        $result = $this->checker($response)->check($service);

        self::assertTrue($result->isUp);
        self::assertSame('HEAD', $response->getRequestMethod());
    }

    private function checker(MockResponse $response): HttpChecker
    {
        return new HttpChecker(new MockHttpClient($response), $this->clock);
    }

    private function service(): MonitoredService
    {
        $project = new Project(new User('owner@example.com', 'Owner'), 'Demo', 'demo');

        return new MonitoredService($project, 'API', 'https://example.com/health');
    }
}
