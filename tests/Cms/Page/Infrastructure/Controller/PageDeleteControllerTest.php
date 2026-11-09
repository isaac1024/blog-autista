<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

final class PageDeleteControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s/delete';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $page = $this->savePage();

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($page, $this->storedPage($page));
    }

    /**
     * A page deleted embeds no image any longer, so its images stop being in
     * use at once.
     */
    public function testItFreesTheImagesThePageEmbeddedAsItDeletesIt(): void
    {
        $client = self::createAuthenticatedClient();
        $page = PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi', content: Text::stored(['type' => 'image', 'id' => '01a1b801-ce58-723d-8234-b76d9105d001']));
        self::getContainer()->get(PageRepository::class)->save($page);
        $relations = self::getContainer()->get(RelationRepository::class);
        $relations->replace(ResourceType::Page, $page->id()->value(), ResourceType::Image, ['01a1b801-ce58-723d-8234-b76d9105d001']);

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertFalse($relations->isUsed(ResourceType::Image, '01a1b801-ce58-723d-8234-b76d9105d001'));
    }

    /**
     * A page deleted links to no page any longer, so the pages it linked to
     * stop being in use at once.
     */
    public function testItFreesThePagesThePageLinkedToAsItDeletesIt(): void
    {
        $client = self::createAuthenticatedClient();
        $page = PageMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi', content: Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Más', 'marks' => ['link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105e001']]]));
        self::getContainer()->get(PageRepository::class)->save($page);
        $relations = self::getContainer()->get(RelationRepository::class);
        $relations->replace(ResourceType::Page, $page->id()->value(), ResourceType::Page, ['01a1b801-ce58-723d-8234-b76d9105e001']);

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertFalse($relations->isUsed(ResourceType::Page, '01a1b801-ce58-723d-8234-b76d9105e001'));
    }

    public function testItGoesBackAndKeepsAPageOtherPagesLinkTo(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();
        self::getContainer()->get(RelationRepository::class)->replace(ResourceType::Page, PageIdMother::random()->value(), ResourceType::Page, [$page->id()->value()]);

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals($page, $this->storedPage($page));
    }

    public function testItDeletesThePageAndGoesBackToTheList(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('/pages', Response::HTTP_SEE_OTHER);
        self::assertNull($this->storedPage($page));
    }

    #[DataProvider('uniquePages')]
    public function testItGoesBackAndKeepsAUniquePage(Page $page): void
    {
        $client = self::createAuthenticatedClient();
        self::getContainer()->get(PageRepository::class)->save($page);

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals($page, $this->storedPage($page));
    }

    /**
     * @return iterable<string, array{Page}>
     */
    public static function uniquePages(): iterable
    {
        yield 'home' => [PageMother::home(id: PageIdMother::random()->value())];

        foreach (PageMother::uniqueButHome(PageIdMother::random()->value()) as $type => $page) {
            yield $type => [$page];
        }
    }

    public function testItGoesBackAndKeepsAPublishedPage(): void
    {
        $client = self::createAuthenticatedClient();
        $page = PageMother::published(id: PageIdMother::random()->value());
        self::getContainer()->get(PageRepository::class)->save($page);

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.$page->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertEquals($page, $this->storedPage($page));
    }

    public function testItOnlyAcceptsPost(): void
    {
        $client = self::createAuthenticatedClient();
        $page = $this->savePage();

        $client->request('GET', \sprintf(self::URL, $page->id()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertEquals($page, $this->storedPage($page));
    }

    public function testItRespondsNotFoundWhenThePageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, PageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_pages_delete', \sprintf(self::URL, 'sobre-mi'));

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
