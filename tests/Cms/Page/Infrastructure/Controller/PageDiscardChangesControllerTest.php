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
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

final class PageDiscardChangesControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;

    private const string URL = 'http://cms.blogautista.localhost/pages/%s/discard';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $page = $this->savePage(PageMother::published(id: PageIdMother::random()->value(), title: 'Quién soy', publishedTitle: 'Sobre mí'));

        $client->request('POST', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($page, $this->storedPage($page));
    }

    public function testItBringsBackThePublishedVersionAndGoesBackToTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        $page = $this->savePage(PageMother::published(id: $id, title: 'Quién soy', publishedTitle: 'Sobre mí', updatedAt: '2026-10-01 09:00:00'));

        $client->request('POST', \sprintf(self::URL, $id));

        self::assertResponseRedirects(\sprintf('/pages/%s', $id), Response::HTTP_SEE_OTHER);
        self::assertEquals(PageMother::published(id: $id, title: 'Sobre mí'), $this->storedPage($page));
    }

    #[DataProvider('pagesWithoutChanges')]
    public function testItGoesBackAndKeepsAPageWithoutChanges(Page $page): void
    {
        $client = self::createAuthenticatedClient();
        $this->savePage($page);

        $client->request('POST', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals($page, $this->storedPage($page));
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function pagesWithoutChanges(): iterable
    {
        yield 'a draft' => [PageMother::create(id: PageIdMother::random()->value())];
        yield 'a published page as it is on the site' => [PageMother::published(id: PageIdMother::random()->value())];
    }

    public function testItOnlyAcceptsPost(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, PageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testItRespondsNotFoundWhenThePageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', \sprintf(self::URL, PageIdMother::random()->value()));

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

    private function savePage(Page $page): Page
    {
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
