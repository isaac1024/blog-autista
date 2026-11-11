<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the form that creates a redirect shows is told by NewRedirectFormTest;
 * here, that it is served, and painted, for every shape it takes.
 */
final class RedirectNewControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/redirects/new';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    #[DataProvider('queries')]
    public function testItOpensTheFormThatCreatesARedirect(string $query): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL.$query);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_redirects_new');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        $source = urlencode('https://www.blogautista.localhost/retirada.html');

        yield 'no address' => [''];
        yield 'an address written' => ['?source='.$source];
        yield 'an address given' => ['?source='.$source.'&fixed=1'];
        yield 'no address, asked to be given' => ['?fixed=1'];
    }
}
