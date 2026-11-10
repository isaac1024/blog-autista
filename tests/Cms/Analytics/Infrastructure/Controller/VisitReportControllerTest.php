<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Infrastructure\Controller;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class VisitReportControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/analytics';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItShowsTheReportWhenNothingWasCounted(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_analytics');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function periods(): iterable
    {
        yield 'the default one' => [''];
        yield 'a week' => ['?days=7'];
        yield 'a month' => ['?days=30'];
        yield 'a quarter' => ['?days=90'];
    }

    #[DataProvider('periods')]
    public function testItShowsTheReportOfEveryPeriod(string $query): void
    {
        $client = self::createAuthenticatedClient();
        $connection = self::getContainer()->get(Connection::class);
        $yesterday = new \DateTimeImmutable('yesterday', new \DateTimeZone('Europe/Madrid'))->format('Y-m-d');
        foreach ([['site', ''], ['page', '01a1b801-ce58-723d-8234-b76d9105f001'], ['channel', 'search'], ['source', 'google.es'], ['device', 'mobile'], ['country', 'AR'], ['country', '']] as [$dimension, $value]) {
            $connection->insert('web_visit_total', ['day' => $yesterday, 'dimension' => $dimension, 'value' => $value, 'views' => 5, 'visitors' => 4]);
        }

        $client->request('GET', self::URL.$query);

        self::assertResponseIsSuccessful();
    }

    public function testItShowsTheReportWhenTheNightlyCountIsLate(): void
    {
        $client = self::createAuthenticatedClient();
        $longAgo = new \DateTimeImmutable('-3 days', new \DateTimeZone('Europe/Madrid'))->format('Y-m-d');
        self::getContainer()->get(Connection::class)->insert('web_visit', ['id' => '01a1b801-ce58-723d-8234-b76d9105f101', 'page_id' => '01a1b801-ce58-723d-8234-b76d9105f001', 'day' => $longAgo, 'channel' => 'direct', 'source' => null, 'reader' => str_repeat('a', 64), 'device' => 'desktop', 'country' => null]);

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
    }

    public function testThereIsNoReportOfAnotherPeriod(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL.'?days=15');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
