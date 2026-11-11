<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the list of redirects shows is told by RedirectListTest; here, that
 * it is served, and painted, for every shape it takes. Which resource a
 * target names is told by NarrowedTargetTest.
 */
final class RedirectListControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/redirects';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheListWithoutAnyRedirect(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_redirects');
    }

    #[DataProvider('queries')]
    public function testItOpensTheListOfRedirects(string $query): void
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        self::getContainer()->get(PageRepository::class)->save(PageMother::published(id: RedirectMother::PAGE, title: 'Una', slug: 'una'));
        self::getContainer()->get(RedirectRepository::class)->save(RedirectMother::create());

        $client->request('GET', self::URL.$query);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        yield 'every redirect' => [''];
        yield 'a search' => ['?source=%20vieja%20'];
        yield 'a page past the end' => ['?page=9'];
        yield 'those leading to a page' => ['?target=page:'.RedirectMother::PAGE];
        yield 'what narrows it to nothing' => ['?target=page:sobre-mi'];
    }

    public function testItAnswersNotFoundForAnInvalidPageNumber(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL.'?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
