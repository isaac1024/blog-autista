<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\TargetInput;
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
        $field = $this->field($prefix, [RedirectTargetField::PAGE, RedirectTargetField::IMAGE], [], []);

        PageComponents::one($field, 'Shared:FormChoice', ['name' => $names['action'], 'id' => $ids['action']]);
        PageComponents::one($field, 'Shared:ResourceField', ['name' => $names['page'], 'id' => $ids['page']]);
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
     * Each kind is picked from what the CMS has of it, by its own search and
     * lookup; sections and tags each from their own taxonomy.
     *
     * @param array{searchUrl: string, lookupUrl: string, key: string, param: string} $endpoints
     */
    #[DataProvider('pickers')]
    public function testItPicksEachKindFromWhatTheCmsHasOfIt(string $kind, array $endpoints): void
    {
        $field = $this->field('', RedirectTargetField::OTHER_KINDS, [], []);

        PageComponents::one($field, 'Shared:ResourceField', [
            'name' => $kind.'_id',
            'id' => 'redirect-'.$kind,
            'label' => 'cms.redirects.form.'.$kind.'.label',
            'texts' => 'ui.resource_field.'.$kind,
            ...$endpoints,
        ]);
    }

    /**
     * @return iterable<string, array{string, array{searchUrl: string, lookupUrl: string, key: string, param: string}}>
     */
    public static function pickers(): iterable
    {
        yield 'a page' => ['page', ['searchUrl' => '/cms_pages_search', 'lookupUrl' => '/cms_pages_lookup', 'key' => 'pages', 'param' => 'title']];
        yield 'a post' => ['post', ['searchUrl' => '/cms_posts_search', 'lookupUrl' => '/cms_posts_lookup', 'key' => 'posts', 'param' => 'title']];
        yield 'a section' => ['section', ['searchUrl' => '/cms_terms_search?taxonomy=sections', 'lookupUrl' => '/cms_terms_lookup?taxonomy=sections', 'key' => 'terms', 'param' => 'name']];
        yield 'a tag' => ['tag', ['searchUrl' => '/cms_terms_search?taxonomy=tags', 'lookupUrl' => '/cms_terms_lookup?taxonomy=tags', 'key' => 'terms', 'param' => 'name']];
        yield 'an author' => ['author', ['searchUrl' => '/cms_authors_search', 'lookupUrl' => '/cms_authors_lookup', 'key' => 'authors', 'param' => 'name']];
    }

    /**
     * An address that isn't of an image is offered every kind but images,
     * and one of an image, only images.
     */
    public function testItOffersWhatAnAddressCanLeadTo(): void
    {
        self::assertSame(['page', 'post', 'section', 'tag', 'author'], RedirectTargetField::kindsFor(false));
        self::assertSame(['image'], RedirectTargetField::kindsFor(true));
    }

    /**
     * A field already leading somewhere holds the resource under the kind it
     * is picked as, and one gone, nothing.
     */
    public function testItHoldsWhatItLeadsTo(): void
    {
        self::assertSame(['action' => 'tag', 'tag_id' => 't'], self::chosen(RedirectTargetField::leadingTo('tag', 't')));
        self::assertSame(['action' => 'gone'], self::chosen(RedirectTargetField::leadingTo(null, 'ignored')));
        self::assertSame(['action' => 'script'], self::chosen(RedirectTargetField::leadingTo('script', 'x')));
    }

    /**
     * The choices of the form it sits in come first and last, around the
     * targets it can lead to and saying it is gone.
     */
    public function testItOffersTheChoicesOfItsFormAroundItsOwn(): void
    {
        $choice = static fn (string $value): array => ['value' => $value, 'label' => $value, 'hint' => $value, 'fields' => []];
        $field = $this->field('', RedirectTargetField::IMAGE_KINDS, [$choice('keep')], [$choice('later')]);

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

        self::assertSame(['action' => 'page', 'page_id' => 'una', 'image_id' => '', 'post_id' => '', 'section_id' => '', 'tag_id' => '', 'author_id' => ''], RedirectTargetField::fromRequest($request, ''));
        $incoming = $request->request->all('incoming')['x'] ?? null;
        self::assertIsArray($incoming);
        self::assertSame(['action' => 'image', 'page_id' => '', 'image_id' => 'otra', 'post_id' => '', 'section_id' => '', 'tag_id' => '', 'author_id' => ''], RedirectTargetField::values($incoming));
    }

    /**
     * The choice names where it leads: the page or the image picked for it,
     * or nowhere; any other choice is a kind of target as it is, and no
     * choice at all leads to no page, which the redirect refuses.
     *
     * @param array<string, string> $values
     */
    #[DataProvider('choices')]
    public function testItTellsWhereTheChoiceLeads(array $values, TargetInput $target): void
    {
        self::assertEquals($target, RedirectTargetField::target(RedirectTargetField::values($values)));
    }

    /**
     * @return iterable<string, array{array<string, string>, TargetInput}>
     */
    public static function choices(): iterable
    {
        yield 'a page' => [['action' => 'page', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput('page', 'p')];
        yield 'an image' => [['action' => 'image', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput('image', 'i')];
        yield 'nowhere' => [['action' => 'gone', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput(null, null)];
        yield 'a post' => [['action' => 'post', 'page_id' => 'p', 'post_id' => 'o'], new TargetInput('post', 'o')];
        yield 'a section, a term' => [['action' => 'section', 'section_id' => 's', 'tag_id' => 't'], new TargetInput('term', 's')];
        yield 'a tag, a term' => [['action' => 'tag', 'section_id' => 's', 'tag_id' => 't'], new TargetInput('term', 't')];
        yield 'an author' => [['action' => 'author', 'author_id' => 'a'], new TargetInput('author', 'a')];
        yield 'another kind' => [['action' => 'script', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput('script', null)];
        yield 'no choice at all' => [['action' => '', 'page_id' => 'p', 'image_id' => 'i'], new TargetInput('page', null)];
    }

    /**
     * The values of a field that hold something.
     *
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    private static function chosen(array $values): array
    {
        return array_filter($values, static fn (string $value): bool => '' !== $value);
    }

    /**
     * @param list<string>                                                                         $types
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
            RedirectTargetField::leadingTo(null, ''),
            $leading,
            $trailing,
        );
        $page = Page::create('Formulario')->withMain($component->name, $component->props, $component->children);
        // Whatever a test asks, the field gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
