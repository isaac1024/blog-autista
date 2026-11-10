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

/**
 * What the editor offers is told by PageEditorTest; here, that it is served,
 * and painted, for every shape of page it composes.
 */
final class PageEditControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, PageIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    /**
     * Each shape the editor takes, with its own fields and actions, is painted.
     */
    #[DataProvider('pagesOfEveryShape')]
    public function testItOpensTheEditorOfThePage(Page $page): void
    {
        $client = self::createAuthenticatedClient();
        self::getContainer()->get(PageRepository::class)->save($page);

        $client->request('GET', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages_edit', ['id' => $page->id()->value()]);
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function pagesOfEveryShape(): iterable
    {
        yield 'a draft' => [PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi')];
        yield 'the home page' => [PageMother::home(id: PageIdMother::random()->value())];
        yield 'a unique page' => [PageMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto', type: PageType::Contact)];
        yield 'a page with content and search engine settings' => [PageMother::create(
            id: PageIdMother::random()->value(),
            title: 'Política de cookies',
            slug: 'politica-cookies',
            content: Text::stored(Text::heading(2, 'Qué cookies usamos'), Text::table(['Nombre'], [['PHPSESSID']])),
            metaDescription: 'Qué cookies usa el blog.',
        )];
        yield 'published' => [PageMother::published(id: PageIdMother::random()->value())];
        yield 'published with changes' => [PageMother::published(id: PageIdMother::random()->value(), title: 'Quién soy', publishedTitle: 'Sobre mí')];
        yield 'unpublished' => [PageMother::unpublished(id: PageIdMother::random()->value())];
        yield 'the published home page' => [PageMother::published(id: PageIdMother::random()->value(), type: PageType::Home)];
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
