<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Infrastructure\Controller\RedirectTargetField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\HttpFoundation\Request;

/**
 * The field that says where an address leads: under which names it posts,
 * and with which ids, so several share a form, and the choices it offers,
 * in order.
 */
final class RedirectTargetFieldTest extends TestCase
{
    /**
     * Without a prefix, it posts its fields as they are; under one, each
     * field goes under it, with ids of its own made of it.
     *
     * @param array{action: string, page: string, image: string} $names
     * @param array{action: string, page: string, image: string} $ids
     */
    #[DataProvider('prefixes')]
    public function testItPostsItsFieldsUnderItsPrefix(string $prefix, array $names, array $ids): void
    {
        $field = $this->field($prefix, [ResourceType::Page, ResourceType::Image], [], []);

        PageComponents::one($field, 'Shared:FormChoice', ['name' => $names['action'], 'id' => $ids['action']]);
        PageComponents::one($field, 'Shared:PageField', ['name' => $names['page'], 'id' => $ids['page']]);
        PageComponents::one($field, 'Shared:ImageField', ['name' => $names['image'], 'id' => $ids['image']]);
    }

    /**
     * @return iterable<string, array{string, array{action: string, page: string, image: string}, array{action: string, page: string, image: string}}>
     */
    public static function prefixes(): iterable
    {
        yield 'none' => [
            '',
            ['action' => 'action', 'page' => 'page_id', 'image' => 'image_id'],
            ['action' => 'redirect-action', 'page' => 'redirect-page', 'image' => 'redirect-image'],
        ];
        yield 'one of an incoming redirect' => [
            'Incoming[01A9-x]',
            ['action' => 'Incoming[01A9-x][action]', 'page' => 'Incoming[01A9-x][page_id]', 'image' => 'Incoming[01A9-x][image_id]'],
            ['action' => 'redirect-incoming-01a9-x-action', 'page' => 'redirect-incoming-01a9-x-page', 'image' => 'redirect-incoming-01a9-x-image'],
        ];
    }

    /**
     * The choices of the form it sits in come first and last, around the
     * targets it can lead to and saying it is gone.
     */
    public function testItOffersTheChoicesOfItsFormAroundItsOwn(): void
    {
        $choice = static fn (string $value): array => ['value' => $value, 'label' => $value, 'hint' => $value, 'fields' => []];
        $field = $this->field('', [ResourceType::Image], [$choice('keep')], [$choice('later')]);

        $options = PageComponents::one($field, 'Shared:FormChoice', [])->props['options'];
        self::assertIsArray($options);
        self::assertSame(['keep', 'image', 'gone', 'later'], array_column($options, 'value'));
    }

    /**
     * What is posted is read as the field posts it, under its prefix, and
     * anything else, or nothing, as empty.
     */
    public function testItReadsWhatIsPostedUnderItsPrefix(): void
    {
        $request = Request::create('/', 'POST', [
            'action' => 'page',
            'page_id' => 'una',
            'incoming' => ['x' => ['action' => 'image', 'image_id' => 'otra', 'page_id' => ['no']]],
        ]);

        self::assertSame(['action' => 'page', 'page_id' => 'una', 'image_id' => ''], RedirectTargetField::fromRequest($request, ''));
        $incoming = $request->request->all('incoming')['x'] ?? null;
        self::assertIsArray($incoming);
        self::assertSame(['action' => 'image', 'page_id' => '', 'image_id' => 'otra'], RedirectTargetField::values($incoming));
    }

    /**
     * The choice names where it leads: the page or the image picked for it,
     * or nowhere; any other choice is a kind of target as it is, and no
     * choice at all leads to no page, which the redirect refuses.
     *
     * @param array{action: string, page_id: string, image_id: string} $values
     */
    #[DataProvider('choices')]
    public function testItTellsWhereTheChoiceLeads(array $values, TargetInput $target): void
    {
        self::assertEquals($target, RedirectTargetField::target($values));
    }

    /**
     * @return iterable<string, array{array{action: string, page_id: string, image_id: string}, TargetInput}>
     */
    public static function choices(): iterable
    {
        yield 'a page' => [['action' => 'page', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput('page', 'p')];
        yield 'an image' => [['action' => 'image', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput('image', 'i')];
        yield 'nowhere' => [['action' => 'gone', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput(null, null)];
        yield 'another kind' => [['action' => 'script', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput('script', null)];
        yield 'no choice at all' => [['action' => '', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput('page', null)];
    }

    /**
     * @param list<ResourceType>                                                                   $types
     * @param list<array{value: string, label: string, hint: string, fields: list<PageComponent>}> $leading
     * @param list<array{value: string, label: string, hint: string, fields: list<PageComponent>}> $trailing
     */
    private function field(string $prefix, array $types, array $leading, array $trailing): Page
    {
        $translator = new CatalogueTranslator();
        $component = new RedirectTargetField($translator, new RouteNameUrlGenerator())(
            $prefix,
            'Adónde lleva',
            $types,
            ['action' => 'gone', 'page_id' => '', 'image_id' => ''],
            $leading,
            $trailing,
        );
        $page = Page::create('Formulario')->withMain($component->name, $component->props, $component->children);
        // Whatever a test asks, the field gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
