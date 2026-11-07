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
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

final class PagePublishControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/pages/%s/publish';

    /**
     * The document the editor posts for PageMother::CONTENT.
     */
    private const string DOCUMENT = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Escribo sobre autismo en primera persona."}]}]}';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $page = $this->savePage(PageMother::create(id: PageIdMother::random()->value()));

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), self::fields());

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($page, $this->storedPage($page));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItPublishesWhatTheFormHoldsAndGoesBackToTheList(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        $page = $this->savePage(PageMother::create(id: $id, title: 'Borrador', slug: 'borrador'));

        $client->request('POST', \sprintf(self::URL, $id), self::fields());

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertEquals(PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi'), $this->storedPage($page));
    }

    public function testItQueuesThePublicationForTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        $this->savePage(PageMother::create(id: $id, title: 'Borrador', slug: 'borrador'));

        $client->request('POST', \sprintf(self::URL, $id), self::fields());

        $events = $this->queuedEvents();
        self::assertCount(1, $events);
        self::assertSame('cms.page.published', $events[0]->name);
        self::assertSame($id, $events[0]->aggregateId);
        self::assertSame('sobre-mi', $events[0]->body['slug']);
        self::assertSame(PageMother::CONTENT, $events[0]->body['content']);
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('unpublishableInputs')]
    public function testItRespondsBadRequestAndKeepsThePageWhenItCannotBePublished(Page $page, array $fields): void
    {
        $client = self::createAuthenticatedClient();
        $this->savePage($page);
        // The request edits the same instance in memory before it fails.
        $expected = clone $page;

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), $fields);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertEquals($expected, $this->storedPage($page));
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{Page, array<string, string>}>
     */
    public static function unpublishableInputs(): iterable
    {
        yield 'without content' => [PageMother::create(id: PageIdMother::random()->value()), self::fields(['content' => '{"type":"doc","content":[{"type":"paragraph"}]}'])];
        yield 'without a meta description' => [PageMother::create(id: PageIdMother::random()->value()), self::fields(['meta_description' => ''])];
        yield 'with an invalid title' => [PageMother::create(id: PageIdMother::random()->value()), self::fields(['title' => ' '])];
        yield 'already published as it is' => [PageMother::published(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi'), self::fields()];
    }

    public function testItRespondsBadRequestWhenAnotherPageIsOnTheSiteWithTheSlug(): void
    {
        $client = self::createAuthenticatedClient();
        // The other page is edited with another slug, but keeps "sobre-mi" on the site.
        $this->savePage(PageMother::published(id: PageIdMother::random()->value(), title: 'Quién soy', slug: 'sobre-mi'));
        $page = $this->savePage(PageMother::create(id: PageIdMother::random()->value(), title: 'Borrador', slug: 'borrador'));
        $expected = clone $page;

        $client->request('POST', \sprintf(self::URL, $page->id()->value()), self::fields());

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertEquals($expected, $this->storedPage($page));
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

        $client->request('POST', \sprintf(self::URL, PageIdMother::random()->value()), self::fields());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Keeps the client's kernel between requests so the fixture, the request
     * and the assertions share one database connection and one queue.
     */
    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * Everything a page needs to be published, unless a test says otherwise.
     *
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private static function fields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Sobre mí',
            'slug' => 'sobre-mi',
            'content' => self::DOCUMENT,
            'meta_description' => PageMother::META_DESCRIPTION,
            'robots' => 'index,follow',
        ], $overrides);
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
