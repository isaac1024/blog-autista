<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
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

final class PageUnpublishControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/pages/%s/unpublish';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $page = $this->savePage(PageMother::published(id: PageIdMother::random()->value()));

        $client->request('POST', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($page, $this->storedPage($page));
    }

    public function testItTakesThePageOffTheSiteAndGoesBackToTheList(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PageIdMother::random()->value();
        $page = $this->savePage(PageMother::published(id: $id));

        $client->request('POST', \sprintf(self::URL, $id));

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertEquals(PageMother::unpublished(id: $id), $this->storedPage($page));

        $events = $this->queuedEvents();
        self::assertCount(1, $events);
        self::assertSame('cms.page.unpublished', $events[0]->name);
        self::assertSame($id, $events[0]->aggregateId);
    }

    #[DataProvider('pagesThatStay')]
    public function testItRespondsBadRequestAndKeepsThePage(Page $page): void
    {
        $client = self::createAuthenticatedClient();
        $this->savePage($page);

        $client->request('POST', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertEquals($page, $this->storedPage($page));
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function pagesThatStay(): iterable
    {
        yield 'a draft' => [PageMother::create(id: PageIdMother::random()->value())];
        yield 'an unpublished page' => [PageMother::unpublished(id: PageIdMother::random()->value())];
        yield 'the published home page' => [PageMother::published(id: PageIdMother::random()->value(), type: PageType::Home)];
        yield 'the published contact page' => [PageMother::published(id: PageIdMother::random()->value(), slug: 'contacto', type: PageType::Contact)];
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
     * and the assertions share one database connection and one queue.
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
