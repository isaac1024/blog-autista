<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageStatus;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\RedirectRetargeter;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publishing a page keeps the redirects true to it, and confirms first
 * whatever it changes of them: it publishes once the editor confirms what
 * it does now, never what it did when the confirmation was shown.
 */
final class PagePublishRedirectsTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s/publish';
    private const string HOST = 'www.blogautista.localhost';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000d1';
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000d2';
    private const string REDIRECT = '0196f3a0-0000-7000-8000-0000000000e1';

    public function testItPublishesAPageForTheFirstTimeAtAnAddressWithARedirectOnceConfirmed(): void
    {
        $client = self::createAuthenticatedClient();
        $this->draftAt('nueva');
        $this->redirect('/nueva.html', self::OTHER);
        $outlook = $this->outlook();

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, self::PAGE), ['outlook' => $outlook]);

        self::assertResponseRedirects('/pages/'.self::PAGE, Response::HTTP_SEE_OTHER);
        self::assertSame(PageStatus::Published, $this->storedStatus());
        self::assertNull($this->redirectAt('/nueva.html'));
    }

    public function testItAsksFirstWhenPublishingStraightFromTheEditorWouldDeleteARedirect(): void
    {
        $client = self::createAuthenticatedClient();
        $this->draftAt('nueva');
        $this->redirect('/nueva.html', self::OTHER);

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, self::PAGE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.self::PAGE.'/publish', Response::HTTP_SEE_OTHER);
        self::assertSame(PageStatus::Draft, $this->storedStatus());
        self::assertNotNull($this->redirectAt('/nueva.html'));
    }

    /**
     * A confirmation sent by hand, of nothing shown, is no confirmation.
     */
    public function testItRefusesAConfirmationOfNothingWhenPublishingDeletesARedirect(): void
    {
        $client = self::createAuthenticatedClient();
        $this->draftAt('nueva');
        $this->redirect('/nueva.html', self::OTHER);

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, self::PAGE), ['outlook' => '']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.self::PAGE.'/publish', Response::HTTP_SEE_OTHER);
        self::assertSame(PageStatus::Draft, $this->storedStatus());
        self::assertNotNull($this->redirectAt('/nueva.html'));
    }

    public function testItAsksAgainWhenTheRedirectItDeletesChangedAfterTheConfirmationWasShown(): void
    {
        $client = self::createAuthenticatedClient();
        $this->draftAt('nueva');
        $this->redirect('/nueva.html', self::OTHER);
        $shown = $this->outlook();
        new RedirectRetargeter(self::REDIRECT, new TargetInput(null, null))(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        );

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, self::PAGE), ['outlook' => $shown]);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.self::PAGE.'/publish', Response::HTTP_SEE_OTHER);
        self::assertSame(PageStatus::Draft, $this->storedStatus());
        self::assertNotNull($this->redirectAt('/nueva.html'));
    }

    public function testItLeadsTheAddressAPageLeavesToItWhenItMoves(): void
    {
        $client = self::createAuthenticatedClient();
        $page = PageMother::published(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi');
        $page->edit('Sobre mí', 'quien-soy', PageMother::CONTENT, PageMother::META_DESCRIPTION, 'index,follow');
        self::getContainer()->get(PageRepository::class)->save($page);

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, self::PAGE), [
            'outlook' => $this->outlook(),
            'previous' => ['action' => 'itself'],
        ]);

        self::assertResponseRedirects('/pages/'.self::PAGE, Response::HTTP_SEE_OTHER);
        self::assertSame(self::PAGE, $this->redirectAt('/sobre-mi.html')?->target()->resource()?->id());
    }

    /**
     * When the page leaves an address, publishing needs to know what it
     * does: a request that doesn't say is refused.
     */
    public function testItRefusesToPublishAPageThatMovesWithoutSayingWhatItsFormerAddressDoes(): void
    {
        $client = self::createAuthenticatedClient();
        $page = PageMother::published(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi');
        $page->edit('Sobre mí', 'quien-soy', PageMother::CONTENT, PageMother::META_DESCRIPTION, 'index,follow');
        self::getContainer()->get(PageRepository::class)->save($page);

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, self::PAGE), ['outlook' => $this->outlook()]);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.self::PAGE.'/publish', Response::HTTP_SEE_OTHER);
        self::assertSame('sobre-mi', $this->storedPublishedSlug());
    }

    public function testItDeletesItsOwnFormerRedirectWithoutAskingWhenItIsBackAtItsAddress(): void
    {
        $client = self::createAuthenticatedClient();
        self::getContainer()->get(PageRepository::class)->save(PageMother::published(id: self::OTHER, title: 'Otra', slug: 'otra'));
        self::getContainer()->get(PageRepository::class)->save(PageMother::unpublished(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $this->redirect('/sobre-mi.html', self::OTHER);
        self::assertSame(self::PAGE, $this->redirectAt('/sobre-mi.html')?->origin()->resource()?->id());

        $this->post($client, 'cms_pages_publish', \sprintf(self::URL, self::PAGE));

        self::assertResponseRedirects('/pages/'.self::PAGE, Response::HTTP_SEE_OTHER);
        self::assertSame(PageStatus::Published, $this->storedStatus());
        self::assertNull($this->redirectAt('/sobre-mi.html'));
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

    private function draftAt(string $slug): void
    {
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(
            id: self::PAGE,
            title: 'Nueva',
            slug: $slug,
            content: PageMother::CONTENT,
            metaDescription: PageMother::META_DESCRIPTION,
        ));
    }

    /**
     * A redirect from the address to a page on the site.
     */
    private function redirect(string $path, string $target): void
    {
        $pages = self::getContainer()->get(PageRepository::class);

        if (null === $pages->find(new PageId($target))) {
            $pages->save(PageMother::published(id: $target, title: 'Otra', slug: 'otra'));
        }

        new RedirectCreator(self::REDIRECT, 'https://'.self::HOST.$path, new TargetInput('page', $target), self::HOST)(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        );
    }

    /**
     * The fingerprint of what the confirmation shows now.
     */
    private function outlook(): string
    {
        return new PublicationOutlookFinder(Resource::page(self::PAGE))(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(SiteResources::class),
        )->fingerprint();
    }

    private function redirectAt(string $path): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->findBySource(Source::fromPath($path));
    }

    private function storedStatus(): ?PageStatus
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PageRepository::class)->find(new PageId(self::PAGE))?->status();
    }

    private function storedPublishedSlug(): ?string
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PageRepository::class)->find(new PageId(self::PAGE))?->publishedSlug()?->value();
    }
}
