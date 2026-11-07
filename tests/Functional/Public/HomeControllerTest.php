<?php

declare(strict_types=1);

namespace App\Tests\Functional\Public;

use App\Factory\ProjectFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;

final class HomeControllerTest extends WebTestCase
{
    use Factories;

    public function testLandingPageRendersWithoutDemoLink(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Sachez quand ça casse.');
        self::assertSelectorExists('a[href="/register"]');
        self::assertSelectorNotExists('a[href="/status/acme-cloud"]');
    }

    public function testLandingPageLinksToPublicDemoProject(): void
    {
        $client = static::createClient();
        ProjectFactory::createOne(['slug' => 'acme-cloud']);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/status/acme-cloud"]');
    }

    public function testLandingPageShowsPricingWithRealQuotasAndFaq(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertSelectorExists('#tarifs');
        self::assertSelectorTextContains('#tarifs', 'Jusqu\'à 5 pages de statut');
        self::assertSelectorTextContains('#tarifs', 'Jusqu\'à 20 services par page');
        self::assertSelectorExists('#faq .accordion-item');
    }

    #[DataProvider('legalPages')]
    public function testLegalPagesAreReachableFromFooter(string $url, string $title): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertSelectorExists(\sprintf('footer a[href="%s"]', $url));

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
    }

    /** @return iterable<array{string, string}> */
    public static function legalPages(): iterable
    {
        yield ['/mentions-legales', 'Mentions légales'];
        yield ['/conditions-utilisation', 'Conditions d\'utilisation'];
        yield ['/confidentialite', 'Politique de confidentialité'];
    }
}
