<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Domain\Origin;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;
use Cms\Redirect\Domain\Timeline;
use Cms\Redirect\Infrastructure\Controller\LastAddressPanel;
use Cms\Redirect\Infrastructure\Controller\ReplacedRedirectNotice;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * What the editor of a page or an image off the site says of its last
 * address: the redirect there, to edit it, or none, and a way to create
 * one; and what publishing a page would do to the redirect at its address.
 * Routes are generated as their names; the texts are their translation
 * keys, with what their placeholders are given.
 */
final class LastAddressPanelTest extends TestCase
{
    use NamesResources;

    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string TARGET = '0196f3a0-0000-7000-8000-0000000000a2';
    private const string REDIRECT = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string ORIGIN = '0196f3a0-0000-7000-8000-0000000000a3';

    /**
     * A resource on the site, or one that never had an address there, has
     * none to tell of.
     */
    public function testItTellsNothingOfAResourceWithoutALastAddress(): void
    {
        foreach ([[true, '/sobre-mi.html'], [false, null]] as [$onSite, $address]) {
            $editor = $this->onPage(onSite: $onSite, address: $address);

            self::assertSame([], PageComponents::find($editor, 'Shared:Panel', []));
        }
    }

    /**
     * The last address of a resource off the site that answers nothing yet
     * can be given a redirect, created at it as the address is given.
     */
    public function testItOffersARedirectAtALastAddressThatAnswersNothing(): void
    {
        foreach ([$this->onPage(onSite: false, address: '/sobre-mi.html'), $this->onImage(onSite: false, address: '/sobre-mi.html')] as $editor) {
            $panel = PageComponents::one($editor, 'Shared:Panel', [
                'title' => 'cms.redirects.last_address.title',
                'description' => 'cms.redirects.last_address.not_found(%address%=/sobre-mi.html)',
            ]);
            PageComponents::one($panel, 'Shared:ButtonLink', [
                'url' => '/cms_redirects_new?'.http_build_query(['source' => RouteNameUrlGenerator::SITE.'/sobre-mi.html', 'fixed' => 1]),
            ]);
        }
    }

    /**
     * The redirect at the last address of a resource off the site is told,
     * with where it leads, and can be edited.
     */
    public function testItLeadsToTheRedirectAtTheLastAddress(): void
    {
        $editor = $this->onPage(onSite: false, address: '/sobre-mi.html', redirect: $this->redirect('/sobre-mi.html'));

        $panel = PageComponents::one($editor, 'Shared:Panel', [
            'title' => 'cms.redirects.last_address.title',
            'description' => 'cms.redirects.last_address.redirected(%address%=/sobre-mi.html, %target%=cms.redirects.target.page(%name%=Destino))',
        ]);
        PageComponents::one($panel, 'Shared:ButtonLink', ['url' => '/cms_redirects_edit?id='.self::REDIRECT]);
    }

    /**
     * Publishing a page at an address a redirect answers deletes the
     * redirect, which its editor tells beforehand; a page whose next address
     * answers nothing is told nothing.
     */
    public function testItTellsAPageThatPublishingDeletesTheRedirectAtItsAddress(): void
    {
        $replacing = $this->onPage(onSite: true, address: null, next: '/quien-soy.html', redirect: $this->redirect('/quien-soy.html'));
        $replacingNothing = $this->onPage(onSite: true, address: null, next: '/quien-soy.html');

        PageComponents::one($replacing, 'Shared:Notice', ['message' => 'cms.redirects.publish.replaced(%address%=/quien-soy.html, %target%=cms.redirects.target.page(%name%=Destino))']);
        self::assertSame([], PageComponents::find($replacingNothing, 'Shared:Notice', []));
    }

    /**
     * Publishing a page at the last address of another resource off the
     * site tells, too, that what links to that one stops showing it.
     */
    public function testItTellsWhatStopsShowingTheResourceWhoseLastAddressIsTaken(): void
    {
        $editor = $this->onPage(onSite: true, address: null, next: '/quien-soy.html', redirect: $this->redirect('/quien-soy.html', Origin::from(Resource::page(self::ORIGIN))));

        PageComponents::one($editor, 'Shared:Notice', ['message' => 'cms.redirects.publish.replaced_origin(%address%=/quien-soy.html, %origin%=cms.redirects.target.page(%name%=Retirada), %target%=cms.redirects.target.page(%name%=Destino))']);
    }

    private function redirect(string $source, ?Origin $origin = null): Redirect
    {
        return new Redirect(
            new RedirectId(self::REDIRECT),
            Source::fromStored($source),
            Target::leadingTo(Resource::page(self::TARGET)),
            $origin ?? Origin::none(),
            new Timeline(ClockMother::now(), ClockMother::now()),
        );
    }

    private function onPage(bool $onSite, ?string $address, ?string $next = null, ?Redirect $redirect = null): Page
    {
        return $this->compose(static fn (LastAddressPanel $panel, Page $layout): Page => $panel->onPage($layout, self::PAGE), $onSite, $address, $next, $redirect);
    }

    private function onImage(bool $onSite, ?string $address): Page
    {
        return $this->compose(static fn (LastAddressPanel $panel, Page $layout): Page => $panel->onImage($layout, self::PAGE), $onSite, $address, null, null);
    }

    /**
     * @param \Closure(LastAddressPanel, Page): Page $on
     */
    private function compose(\Closure $on, bool $onSite, ?string $address, ?string $next, ?Redirect $redirect): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $siteResources = self::createStub(SiteResources::class);
        $siteResources->method('isOnSite')->willReturn($onSite);
        $siteResources->method('addressOf')->willReturn(null === $address ? null : Source::fromStored($address));
        $siteResources->method('nextAddressOf')->willReturn(null === $next ? null : Source::fromStored($next));
        $redirectRepository = self::createStub(RedirectRepository::class);
        $redirectRepository->method('findBySource')->willReturnCallback(
            static fn (Source $source): ?Redirect => null !== $redirect && $redirect->source()->equals($source) ? $redirect : null,
        );
        $redirectNames = $this->redirectNames($translator, [
            PageMother::published(id: self::TARGET, title: 'Destino', slug: 'destino'),
            PageMother::unpublished(id: self::ORIGIN, title: 'Retirada', slug: 'retirada'),
        ]);

        $page = $on(new LastAddressPanel(
            $redirectRepository,
            $siteResources,
            $redirectNames,
            $translator,
            $urlGenerator,
            new ReplacedRedirectNotice($redirectNames, $translator),
        ), Page::create('Editor'));
        // Whatever a test asks, the panel gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
