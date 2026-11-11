<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the editor of a redirect shows is told by RedirectEditorTest; here,
 * that it is served, and painted, for every shape a redirect takes.
 */
final class RedirectEditControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/redirects/%s';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, RedirectIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    #[DataProvider('redirects')]
    public function testItOpensTheEditorOfTheRedirect(Redirect $redirect): void
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: RedirectMother::PAGE, title: 'Una', slug: 'una'));
        $pages->save(PageMother::unpublished(id: RedirectMother::OFF_SITE, title: 'Retirada', slug: 'retirada'));
        self::getContainer()->get(RedirectRepository::class)->save($redirect);

        $client->request('GET', \sprintf(self::URL, $redirect->id()->value()));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_redirects_edit', ['id' => $redirect->id()->value()]);
    }

    /**
     * @return iterable<string, array{Redirect}>
     */
    public static function redirects(): iterable
    {
        yield 'leading to a page' => [RedirectMother::create()];
        yield 'gone' => [RedirectMother::gone()];
        yield 'at the last address of a page off the site' => [RedirectMother::create(source: '/retirada.html', origin: Resource::page(RedirectMother::OFF_SITE))];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknownRedirects(): iterable
    {
        yield 'an id of none' => [RedirectIdMother::DEFAULT_VALUE];
        yield 'no id at all' => ['vieja'];
    }

    #[DataProvider('unknownRedirects')]
    public function testItRespondsNotFoundWhenTheRedirectDoesNotExist(string $id): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
