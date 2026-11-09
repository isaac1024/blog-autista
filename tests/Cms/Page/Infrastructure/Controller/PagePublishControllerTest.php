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

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($page, $this->storedPage($page));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItPublishesTheSavedVersionAndStaysInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        $page = $this->savePage(self::publishableDraft($id));

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, $id));

        self::assertResponseRedirects('/pages/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.page.published', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(PageMother::published(id: $id, title: 'Sobre mí', slug: 'sobre-mi'), $this->storedPage($page));
    }

    public function testItQueuesThePublicationForTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        $this->savePage(self::publishableDraft($id));

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, $id));

        $events = $this->queuedEvents();
        self::assertCount(1, $events);
        self::assertSame('cms.page.published', $events[0]->name);
        self::assertSame($id, $events[0]->aggregateId);
        self::assertSame('sobre-mi', $events[0]->body['slug']);
        self::assertSame(PageMother::CONTENT, $events[0]->body['content']);
    }

    #[DataProvider('unpublishablePages')]
    public function testItGoesBackAndKeepsThePageWhenItCannotBePublished(Page $page): void
    {
        $client = self::createAuthenticatedClient();
        $this->savePage($page);
        $expected = clone $page;

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals($expected, $this->storedPage($page));
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function unpublishablePages(): iterable
    {
        yield 'without content' => [PageMother::create(id: PageIdMother::random()->value(), metaDescription: PageMother::META_DESCRIPTION)];
        yield 'without a meta description' => [PageMother::create(id: PageIdMother::random()->value(), content: PageMother::CONTENT)];
        yield 'already published as it is' => [PageMother::published(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi')];
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

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, PageIdMother::random()->value()));

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

    private static function publishableDraft(string $id): Page
    {
        return PageMother::create(id: $id, title: 'Sobre mí', slug: 'sobre-mi', content: PageMother::CONTENT, metaDescription: PageMother::META_DESCRIPTION);
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
