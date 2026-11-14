<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Infrastructure\Controller\RedirectTargetField;
use Cms\Redirect\Infrastructure\Controller\RetirementForm;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * What the form that takes a resource off the site asks: what its own
 * address does, when it has one, and what each redirect to it does
 * instead, choosing again what was refused. Nothing is chosen for the
 * editor. The texts are their translation keys, with what their
 * placeholders are given.
 */
final class RetirementFormTest extends TestCase
{
    use NamesResources;

    private const string PAGE = RedirectMother::PAGE;
    private const string IMAGE = RedirectMother::IMAGE;
    private const string INCOMING = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string FROM_ORIGIN = '0196f3a0-0000-7000-8000-0000000000e2';

    public function testItPostsWhereItIsToldWithItsTokenAndADangerousButton(): void
    {
        $form = PageComponents::one($this->form(Resource::page(self::PAGE)), 'Shared:Form', ['action' => '/despublicar', 'method' => 'post', 'csrfTokenId' => 'cms_pages_unpublish']);

        PageComponents::one($form, 'Shared:FormButton', ['label' => 'Despublicar', 'variant' => 'danger']);
    }

    public function testItAsksWhatTheAddressOfAPageDoesChoosingNothing(): void
    {
        $page = $this->form(Resource::page(self::PAGE), address: '/sobre-mi.html');

        $panel = PageComponents::one($page, 'Shared:Panel', [
            'title' => 'cms.redirects.retire.address.title',
            'description' => 'cms.redirects.retire.address.description(%address%=/sobre-mi.html)',
        ]);
        $choice = PageComponents::one($panel, 'Shared:FormChoice', ['name' => 'address[action]', 'legend' => 'cms.redirects.retire.address.legend', 'value' => '']);
        self::assertSame([...RedirectTargetField::OTHER_KINDS, RedirectTargetField::GONE, 'none'], self::options($choice));
    }

    /**
     * The address of an image only leads to another image.
     */
    public function testItOffersTheAddressOfAnImageOnlyAnotherImage(): void
    {
        $page = $this->form(Resource::image(self::IMAGE), address: '/media/2026/11/'.self::IMAGE);

        self::assertSame(['image', RedirectTargetField::GONE, 'none'], self::options(PageComponents::one($page, 'Shared:FormChoice', ['name' => 'address[action]'])));
    }

    public function testItTellsAResourceWithoutAnAddressHasNoneToLead(): void
    {
        $page = $this->form(Resource::page(self::PAGE));

        $panel = PageComponents::one($page, 'Shared:Panel', ['title' => 'cms.redirects.retire.address.title', 'description' => 'cms.redirects.retire.address.none']);
        self::assertSame([], PageComponents::find($panel, 'Shared:FormChoice', []));
    }

    public function testItAsksNothingOfRedirectsWhenNoneLeadsToIt(): void
    {
        self::assertSame([], PageComponents::find($this->form(Resource::page(self::PAGE)), 'Shared:Panel', ['title' => 'cms.redirects.retire.incoming.title']));
    }

    /**
     * Each redirect is asked of apart, telling what else follows it: what
     * links to the resource that was there, if it still exists.
     */
    public function testItAsksWhatEachRedirectToItDoesInstead(): void
    {
        $page = $this->form(Resource::page(self::PAGE), incoming: [
            RedirectMother::create(id: self::INCOMING, source: '/vieja.html'),
            RedirectMother::create(id: self::FROM_ORIGIN, source: '/retirada.html', origin: Resource::page(RedirectMother::OFF_SITE)),
        ]);

        $panel = PageComponents::one($page, 'Shared:Panel', ['title' => 'cms.redirects.retire.incoming.title', 'description' => 'cms.redirects.retire.incoming.description(%count%=2)']);
        $plain = PageComponents::one($panel, 'Shared:FormChoice', ['name' => 'incoming['.self::INCOMING.'][action]', 'legend' => 'cms.redirects.retire.incoming.legend(%source%=/vieja.html)', 'value' => '']);
        PageComponents::one($panel, 'Shared:FormChoice', [
            'name' => 'incoming['.self::FROM_ORIGIN.'][action]',
            'legend' => 'cms.redirects.retire.incoming.legend_origin(%origin%=cms.redirects.target.page(%name%=Retirada), %source%=/retirada.html)',
        ]);
        self::assertSame(['same', ...RedirectTargetField::OTHER_KINDS, RedirectTargetField::GONE, 'delete'], self::options($plain));
    }

    public function testItOffersTheRedirectOfAnImageOnlyAnotherImage(): void
    {
        $page = $this->form(Resource::image(self::IMAGE), incoming: [RedirectMother::create(id: self::INCOMING, source: '/media/2026/11/0196f3a0-0000-7000-8000-0000000000c9', target: Resource::image(self::IMAGE))]);

        self::assertSame(['same', 'image', RedirectTargetField::GONE, 'delete'], self::options(PageComponents::one($page, 'Shared:FormChoice', ['name' => 'incoming['.self::INCOMING.'][action]'])));
    }

    public function testItChoosesAgainWhatWasRefused(): void
    {
        $page = $this->form(Resource::page(self::PAGE), address: '/sobre-mi.html', incoming: [RedirectMother::create(id: self::INCOMING)], posted: [
            'address' => ['action' => 'gone'],
            'incoming' => [self::INCOMING => ['action' => 'delete']],
        ]);

        PageComponents::one($page, 'Shared:FormChoice', ['name' => 'address[action]', 'value' => 'gone']);
        PageComponents::one($page, 'Shared:FormChoice', ['name' => 'incoming['.self::INCOMING.'][action]', 'value' => 'delete']);
    }

    /**
     * What was sent of another shape is chosen as nothing.
     */
    public function testItChoosesNothingOfWhatWasSentInAnotherShape(): void
    {
        $page = $this->form(Resource::page(self::PAGE), address: '/sobre-mi.html', incoming: [RedirectMother::create(id: self::INCOMING)], posted: ['address' => 'gone', 'incoming' => 'delete']);

        PageComponents::one($page, 'Shared:FormChoice', ['name' => 'address[action]', 'value' => '']);
        PageComponents::one($page, 'Shared:FormChoice', ['name' => 'incoming['.self::INCOMING.'][action]', 'value' => '']);
    }

    /**
     * @return list<string>
     */
    private static function options(PageComponent $choice): array
    {
        $options = $choice->props['options'];
        self::assertIsArray($options);

        return array_map(static fn (mixed $option): string => \is_array($option) && \is_string($option['value'] ?? null) ? $option['value'] : '', array_values($options));
    }

    /**
     * @param list<Redirect>          $incoming the redirects that lead to the resource
     * @param array<array-key, mixed> $posted
     */
    private function form(Resource $resource, ?string $address = null, array $incoming = [], array $posted = []): Page
    {
        $translator = new CatalogueTranslator();
        $redirectRepository = self::createStub(RedirectRepository::class);
        $redirectRepository->method('findTargeting')->willReturn($incoming);
        $siteResources = self::createStub(SiteResources::class);
        $siteResources->method('addressOf')->willReturn(null === $address ? null : Source::fromStored($address));

        $components = new RetirementForm(
            $translator,
            new RedirectTargetField($translator, new RouteNameUrlGenerator()),
            $redirectRepository,
            $siteResources,
            $this->redirectNames($translator, [PageMother::unpublished(id: RedirectMother::OFF_SITE, title: 'Retirada', slug: 'retirada')]),
        )($resource, '/despublicar', 'cms_pages_unpublish', 'Despublicar', $posted);

        $page = Page::create('Despublicar');

        foreach ($components as $component) {
            $page = $page->withMain($component->name, $component->props, $component->children);
        }

        // Whatever a test asks, the form gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
