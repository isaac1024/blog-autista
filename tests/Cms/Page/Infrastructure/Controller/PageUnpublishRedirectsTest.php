<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Application\PageUnpublisher;
use Cms\Page\Domain\InvalidPublication;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRedirects;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageStatus;
use Cms\Page\Infrastructure\Controller\PageUnpublishFormController;
use Cms\Redirect\Application\IncomingDecision;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\ResourceRetirement;
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
use Shared\Domain\Transaction;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Taking a page off the site decides first what every redirect to it does,
 * since none may lead to a page off the site, then what its own address
 * does; all of it, or nothing, when a choice is refused.
 */
final class PageUnpublishRedirectsTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/pages/%s/unpublish';
    private const string HOST = 'www.blogautista.localhost';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string THIRD = '0196f3a0-0000-7000-8000-0000000000b2';
    private const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000b3';
    private const string INCOMING = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string SECOND = '0196f3a0-0000-7000-8000-0000000000e2';

    public function testItKeepsThePageOnTheSiteWhileARedirectToItIsUndecided(): void
    {
        $client = self::createAuthenticatedClient();
        $this->pageWithIncomingRedirect();
        $choices = ['address' => ['action' => 'page', 'page_id' => self::OTHER]];

        $this->post($client, 'cms_pages_unpublish', \sprintf(self::URL, self::PAGE), $choices);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.self::PAGE.'/unpublish', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.redirect.undecided', 'parameters' => ['%source%' => '/vieja.html']]], self::errorNotices($client));
        self::assertSame(PageStatus::Published, $this->storedStatus());
        self::assertSame(self::PAGE, $this->redirectAt('/vieja.html')?->target()->resource()?->id());
        // The form comes back with the choices made.
        $saved = $client->getRequest()->getSession()->get(\sprintf(PageUnpublishFormController::SAVED, self::PAGE));
        self::assertIsArray($saved);
        self::assertSame($choices['address'], $saved['address'] ?? null);
    }

    public function testItLeadsTheRedirectsToItWhereItsAddressLeadsAndTakesItOff(): void
    {
        $client = self::createAuthenticatedClient();
        $this->pageWithIncomingRedirect();

        $this->post($client, 'cms_pages_unpublish', \sprintf(self::URL, self::PAGE), [
            'address' => ['action' => 'page', 'page_id' => self::OTHER],
            'incoming' => [self::INCOMING => ['action' => 'same']],
        ]);

        self::assertResponseRedirects('/pages/'.self::PAGE, Response::HTTP_SEE_OTHER);
        self::assertSame(PageStatus::Unpublished, $this->storedStatus());
        self::assertSame(self::OTHER, $this->redirectAt('/vieja.html')?->target()->resource()?->id());
        $own = $this->redirectAt('/sobre-mi.html');
        self::assertNotNull($own);
        self::assertSame(self::OTHER, $own->target()->resource()?->id());
        self::assertSame(self::PAGE, $own->origin()->resource()?->id());
    }

    /**
     * Off the site, a page can't be where a redirect to it leads instead.
     */
    public function testItRefusesARedirectToItLedToItselfAndChangesNothing(): void
    {
        $client = self::createAuthenticatedClient();
        $this->pageWithIncomingRedirect();

        $this->post($client, 'cms_pages_unpublish', \sprintf(self::URL, self::PAGE), [
            'address' => ['action' => 'none'],
            'incoming' => [self::INCOMING => ['action' => 'page', 'page_id' => self::PAGE]],
        ]);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages/'.self::PAGE.'/unpublish', Response::HTTP_SEE_OTHER);
        self::assertSame(['errors.redirect.to_itself'], self::errorKeys($client));
        self::assertSame(PageStatus::Published, $this->storedStatus());
    }

    /**
     * A redirect to it sent with nothing chosen is as undecided as one not
     * sent at all.
     */
    public function testItTakesARedirectToItSentWithNothingChosenAsUndecided(): void
    {
        $client = self::createAuthenticatedClient();
        $this->pageWithIncomingRedirect();

        $this->post($client, 'cms_pages_unpublish', \sprintf(self::URL, self::PAGE), [
            'address' => ['action' => 'none'],
            'incoming' => [self::INCOMING => ['action' => '']],
        ]);

        self::assertSame(['errors.redirect.undecided'], self::errorKeys($client));
        self::assertSame(PageStatus::Published, $this->storedStatus());
    }

    public function testItDeletesOrLeadsEachRedirectToItAsDecided(): void
    {
        $client = self::createAuthenticatedClient();
        $this->pageWithIncomingRedirect();
        $this->incoming(self::SECOND, '/otra-vieja.html');

        $this->post($client, 'cms_pages_unpublish', \sprintf(self::URL, self::PAGE), [
            'address' => ['action' => 'none'],
            'incoming' => [self::INCOMING => ['action' => 'delete'], self::SECOND => ['action' => 'gone']],
        ]);

        self::assertSame(PageStatus::Unpublished, $this->storedStatus());
        self::assertNull($this->redirectAt('/vieja.html'));
        $second = $this->redirectAt('/otra-vieja.html');
        self::assertNotNull($second);
        self::assertNull($second->target()->resource());
        // Left not found, its own address has no redirect.
        self::assertNull($this->redirectAt('/sobre-mi.html'));
    }

    /**
     * A choice refused once something was changed undoes it all: the page,
     * and every redirect already led elsewhere, are as they were.
     */
    public function testItUndoesEverythingWhenAChoiceIsRefusedOnceSomethingChanged(): void
    {
        $client = self::createAuthenticatedClient();
        $this->pageWithIncomingRedirect();
        self::getContainer()->get(PageRepository::class)->save(PageMother::unpublished(id: self::OFF_SITE, title: 'Retirada', slug: 'retirada'));

        // The redirect to it is led first, then the page taken off; only then
        // is its own address led, to a page off the site.
        $this->post($client, 'cms_pages_unpublish', \sprintf(self::URL, self::PAGE), [
            'address' => ['action' => 'page', 'page_id' => self::OFF_SITE],
            'incoming' => [self::INCOMING => ['action' => 'gone']],
        ]);

        self::assertSame(['errors.redirect.target_off_site'], self::errorKeys($client));
        self::assertSame(PageStatus::Published, $this->storedStatus());
        self::assertSame(self::PAGE, $this->redirectAt('/vieja.html')?->target()->resource()?->id());
    }

    /**
     * It holds the page, its address and where it will lead, the address of
     * every redirect to it, and where each will lead.
     */
    public function testItHoldsThePageItsAddressAndEveryRedirectToItWhileTakingItOff(): void
    {
        self::createAuthenticatedClient();
        $this->pageWithIncomingRedirect();
        $this->incoming(self::SECOND, '/otra-vieja.html');
        $mutualExclusion = new RecordingMutualExclusion();

        new ResourceRetirement(
            Resource::page(self::PAGE),
            new TargetInput('page', self::OTHER),
            [self::INCOMING => IncomingDecision::sameAsAddress(), self::SECOND => IncomingDecision::leadingTo(new TargetInput('page', self::THIRD))],
            fn () => new PageUnpublisher(self::PAGE)(self::getContainer()->get(PageRepository::class), self::getContainer()->get(EventBus::class), self::getContainer()->get(PageRedirects::class)),
        )(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(SiteResources::class),
            $mutualExclusion,
            self::getContainer()->get(Transaction::class),
        );

        self::assertSame([
            'address:/otra-vieja.html',
            'address:/sobre-mi.html',
            'address:/vieja.html',
            'page:'.self::PAGE,
            'page:'.self::OTHER,
            'page:'.self::THIRD,
        ], $mutualExclusion->lastHeld());
    }

    public function testItRefusesToTakeOffAPageARedirectLeadsToWithoutTheForm(): void
    {
        $this->pageWithIncomingRedirect();

        $this->expectExceptionObject(InvalidPublication::redirected());

        new PageUnpublisher(self::PAGE)(
            self::getContainer()->get(PageRepository::class),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(PageRedirects::class),
        );
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

    /**
     * A page on the site at /sobre-mi.html, two others to lead to, and a
     * redirect from /vieja.html to the first.
     */
    private function pageWithIncomingRedirect(): void
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $pages->save(PageMother::published(id: self::OTHER, title: 'Otra', slug: 'otra'));
        $pages->save(PageMother::published(id: self::THIRD, title: 'Tercera', slug: 'tercera'));
        $this->incoming(self::INCOMING, '/vieja.html');
    }

    /**
     * A redirect from the address to the page.
     */
    private function incoming(string $id, string $path): void
    {
        new RedirectCreator($id, 'https://'.self::HOST.$path, new TargetInput('page', self::PAGE), self::HOST)(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        );
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
}
