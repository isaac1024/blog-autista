<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\RedirectsPage;
use Cms\Redirect\Domain\Origin;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;
use Cms\Redirect\Domain\Timeline;
use Cms\Redirect\Infrastructure\Controller\IncomingRedirectsPanel;
use Cms\Redirect\Infrastructure\Controller\RedirectRows;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * What the page that tells where a resource is used says of the redirects
 * leading to it: none, the first of them, or the first with a way to all.
 * Routes are generated as their names; the texts are their translation
 * keys, with what their placeholders are given.
 */
final class IncomingRedirectsPanelTest extends TestCase
{
    use NamesResources;

    private const string RESOURCE = '0196f3a0-0000-7000-8000-0000000000a1';

    /**
     * With no redirect leading to it, the panel says so of the resource.
     */
    #[DataProvider('types')]
    public function testItTellsNoRedirectLeadsToTheResource(ResourceType $type): void
    {
        $panel = $this->panel($type, 0);

        PageComponents::one($panel, 'Shared:Panel', [
            'title' => 'cms.redirects.incoming.title',
            'description' => \sprintf('cms.redirects.incoming.%s.empty(%%name%%=Sobre mí)', $type->value),
        ]);
        self::assertSame([], PageComponents::find($panel, 'Shared:ResourceList', []));
    }

    /**
     * The redirects leading to the resource are listed, the first page of
     * them; when more follow, the list of redirects narrowed to it has them
     * all.
     */
    #[DataProvider('counts')]
    public function testItListsTheRedirectsLeadingToTheResource(ResourceType $type, int $count, int $listed, bool $all): void
    {
        $panel = $this->panel($type, $count);

        $panelComponent = PageComponents::one($panel, 'Shared:Panel', [
            'title' => 'cms.redirects.incoming.title',
            'description' => \sprintf('cms.redirects.incoming.%s.description(%%name%%=Sobre mí)', $type->value),
        ]);
        $list = PageComponents::one($panelComponent, 'Shared:ResourceList', ['label' => 'cms.redirects.incoming.title']);
        self::assertCount($listed, $list->children);
        self::assertCount($all ? 1 : 0, PageComponents::find($panelComponent, 'Shared:ButtonLink', [
            'url' => '/cms_redirects?'.http_build_query(['target' => $type->value.':'.self::RESOURCE]),
        ]));
    }

    /**
     * @return iterable<string, array{ResourceType}>
     */
    public static function types(): iterable
    {
        yield 'a page' => [ResourceType::Page];
        yield 'an image' => [ResourceType::Image];
    }

    /**
     * @return iterable<string, array{ResourceType, int, int, bool}>
     */
    public static function counts(): iterable
    {
        yield 'one to a page' => [ResourceType::Page, 1, 1, false];
        yield 'a page full to an image' => [ResourceType::Image, RedirectsPage::PAGE_SIZE, RedirectsPage::PAGE_SIZE, false];
        yield 'more than a page to a page' => [ResourceType::Page, RedirectsPage::PAGE_SIZE + 1, RedirectsPage::PAGE_SIZE, true];
    }

    private function panel(ResourceType $type, int $count): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $redirects = array_map(
            static fn (int $number): Redirect => new Redirect(
                RedirectId::random(),
                Source::fromStored(\sprintf('/vieja-%d.html', $number)),
                Target::leadingTo(new Resource($type, self::RESOURCE)),
                Origin::none(),
                new Timeline(ClockMother::now(), ClockMother::now()),
            ),
            $count > 0 ? range(1, $count) : [],
        );
        $redirectRepository = self::createStub(RedirectRepository::class);
        // The repository answers the slice the criteria ask for.
        $redirectRepository->method('matching')->willReturnCallback(
            static fn (Criteria $criteria): array => \array_slice($redirects, $criteria->offset, $criteria->limit),
        );

        $component = new IncomingRedirectsPanel(
            $redirectRepository,
            new RedirectRows($this->redirectNames($translator), $urlGenerator, $translator),
            $translator,
            $urlGenerator,
        )($type, self::RESOURCE, 'Sobre mí');
        $page = Page::create('Usos')->withMain($component->name, $component->props, $component->children);
        // Whatever a test asks, the panel gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
