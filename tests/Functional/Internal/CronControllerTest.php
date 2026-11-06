<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** F-13 */
final class CronControllerTest extends WebTestCase
{
    /** @return iterable<string, array{array<string, string>}> */
    public static function invalidHeaders(): iterable
    {
        yield 'sans jeton' => [[]];
        yield 'jeton vide' => [['HTTP_X_CRON_TOKEN' => '']];
        yield 'mauvais jeton' => [['HTTP_X_CRON_TOKEN' => 'wrong-token']];
    }

    /** @param array<string, string> $headers */
    #[DataProvider('invalidHeaders')]
    public function testRunChecksRequiresValidToken(array $headers): void
    {
        $client = static::createClient();
        $client->request('POST', '/internal/cron/run-checks', server: $headers);

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetIsNotAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/internal/cron/run-checks');

        self::assertResponseStatusCodeSame(405);
    }
}
