<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\HttpFoundation\Response;

final class PageEditControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, PageIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheEditorOfAnExistingPage(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi'));

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages_edit', ['id' => $id]);
    }

    public function testItOpensTheEditorOfTheHomePage(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::home(id: $id));

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseIsSuccessful();
    }

    public function testItOpensTheEditorOfAUniquePage(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(
            PageMother::create(id: $id, title: 'Contacto', slug: 'contacto', type: PageType::Contact),
        );

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseIsSuccessful();
    }

    public function testItOpensTheEditorOfAPageWithContentAndSearchEngineSettings(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(
            id: $id,
            title: 'Política de cookies',
            slug: 'politica-cookies',
            content: Text::stored(Text::heading(2, 'Qué cookies usamos'), Text::table(['Nombre'], [['PHPSESSID']])),
            metaDescription: 'Qué cookies usa el blog.',
        ));

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseIsSuccessful();
    }

    /**
     * Each state offers its own actions.
     */
    #[DataProvider('pagesInEveryState')]
    public function testItOpensTheEditorWhereverThePageStands(Page $page): void
    {
        $client = self::createAuthenticatedClient();
        self::getContainer()->get(PageRepository::class)->save($page);

        $client->request('GET', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function pagesInEveryState(): iterable
    {
        yield 'published' => [PageMother::published(id: PageIdMother::random()->value())];
        yield 'published with changes' => [PageMother::published(id: PageIdMother::random()->value(), title: 'Quién soy', publishedTitle: 'Sobre mí')];
        yield 'unpublished' => [PageMother::unpublished(id: PageIdMother::random()->value())];
        yield 'a published unique page' => [PageMother::published(id: PageIdMother::random()->value(), type: PageType::Home)];
    }

    public function testItRespondsNotFoundWhenThePageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, PageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, 'sobre-mi'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
