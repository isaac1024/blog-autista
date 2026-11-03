<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

final class PageUpdateControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $page = $this->savePage();

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), ['title' => 'Quién soy', 'slug' => 'quien-soy']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($page, $this->storedPage($page));
    }

    public function testItSavesTheChangesAndGoesBackToTheList(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), ['title' => 'Quién soy', 'slug' => 'quien-soy']);

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertEquals(
            PageMother::create(id: $page->id()->value(), title: 'Quién soy', slug: 'quien-soy'),
            $this->storedPage($page),
        );
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('invalidFields')]
    public function testItRespondsBadRequestAndKeepsThePageWhenTheInputIsInvalid(array $fields): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), $fields);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertEquals($page, $this->storedPage($page));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidFields(): iterable
    {
        yield 'invalid title' => [['title' => ' ', 'slug' => 'quien-soy']];
        yield 'invalid slug' => [['title' => 'Quién soy', 'slug' => 'Quién Soy']];
        yield 'missing title' => [['slug' => 'quien-soy']];
        yield 'missing slug' => [['title' => 'Quién soy']];
    }

    public function testItRespondsBadRequestAndKeepsThePageWhenAnotherPageHasTheSlug(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();
        self::getContainer()->get(PageRepository::class)->save(
            PageMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto'),
        );

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), ['title' => 'Quién soy', 'slug' => 'contacto']);

        // The request edits the very instance saved above before the slug check
        // fails, so the stored page is compared against a fresh copy instead.
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertEquals(
            PageMother::create(id: $page->id()->value(), title: 'Sobre mí', slug: 'sobre-mi'),
            $this->storedPage($page),
        );
    }

    public function testItSavesTheHomePageWithoutASlug(): void
    {
        $client = self::createAuthenticatedClient();
        $home = PageMother::home(id: PageIdMother::random()->value(), title: 'Portada');
        self::getContainer()->get(PageRepository::class)->save($home);

        // The editor of the home page has no slug field, so none is posted.
        $client->request('POST', \sprintf(self::URL, $home->id()->value()), ['title' => 'Inicio']);

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertEquals(PageMother::home(id: $home->id()->value(), title: 'Inicio'), $this->storedPage($home));
    }

    public function testItRespondsBadRequestAndKeepsTheHomePageWhenItIsGivenASlug(): void
    {
        $client = self::createAuthenticatedClient();
        $home = PageMother::home(id: PageIdMother::random()->value(), title: 'Portada');
        self::getContainer()->get(PageRepository::class)->save($home);

        $client->request('POST', \sprintf(self::URL, $home->id()->value()), ['title' => 'Inicio', 'slug' => 'inicio']);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertEquals(PageMother::home(id: $home->id()->value(), title: 'Portada'), $this->storedPage($home));
    }

    public function testItRespondsNotFoundWhenThePageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', \sprintf(self::URL, PageIdMother::random()->value()), ['title' => 'Quién soy', 'slug' => 'quien-soy']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', \sprintf(self::URL, 'sobre-mi'), ['title' => 'Quién soy', 'slug' => 'quien-soy']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Keeps the client's kernel between requests so the fixture, the request
     * and the assertions share one database connection.
     */
    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    private function savePage(): Page
    {
        $page = PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi');
        self::getContainer()->get(PageRepository::class)->save($page);

        return $page;
    }

    /**
     * Reads the page back from the database, not from Doctrine's identity map.
     */
    private function storedPage(Page $page): ?Page
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PageRepository::class)->find($page->id());
    }
}
