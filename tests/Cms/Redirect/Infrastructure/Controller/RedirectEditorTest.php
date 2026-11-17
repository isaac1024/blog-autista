<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Page\Domain\Page as PageAggregate;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Infrastructure\Controller\RedirectEditor;
use Cms\Shared\Infrastructure\Publication\RedirectTargetField;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the editor of a redirect makes: what it tells of the
 * address, where it lets it lead, and what deleting it means. Routes are
 * generated as their names; the texts are their translation keys, with
 * what their placeholders are given.
 */
final class RedirectEditorTest extends TestCase
{
    use NamesResources;

    private const string ID = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string TAG = '0196f3a0-0000-7000-8000-0000000000d1';
    private const string ORIGIN = '0196f3a0-0000-7000-8000-0000000000a2';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000b1';

    /**
     * The redirect is named by its address, with when it was created and
     * last changed, and whether it leads somewhere or tells it is gone.
     */
    #[DataProvider('targets')]
    public function testItTellsOfTheAddressAndWhetherItLeadsSomewhere(?string $targetType, ?string $targetId, string $status): void
    {
        $editor = $this->edit($this->redirect(targetType: $targetType, targetId: $targetId));

        PageComponents::one($editor, 'Shared:PageTitle', [
            'title' => '/vieja.html',
            'subtitle' => 'cms.redirects.edit.dates(%created%=1 oct 2026, %updated%=9 nov 2026)',
            'status' => $status,
        ]);
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function targets(): iterable
    {
        yield 'leading to a page' => ['page', self::PAGE, 'cms.redirects.status.moved'];
        yield 'gone' => [null, null, 'cms.redirects.status.gone'];
    }

    public function testItLeadsBackToEveryRedirect(): void
    {
        $editor = $this->edit($this->redirect());

        PageComponents::one($editor, 'Shared:ButtonLink', ['url' => '/cms_redirects']);
    }

    /**
     * When the address was the last of a resource off the site, the editor
     * says so, and so does deleting it, since what links to that resource
     * follows the redirect too; a resource deleted since has nothing linking
     * to it.
     *
     * @param list<PageAggregate> $pages
     */
    #[DataProvider('origins')]
    public function testItTellsWhatFollowsTheRedirectFromTheResourceThatWasThere(?string $originId, array $pages, ?string $notice, string $deleting): void
    {
        $editor = $this->edit($this->redirect(originType: null === $originId ? null : 'page', originId: $originId), $pages);

        self::assertSame(
            null === $notice ? [] : [$notice],
            array_map(static fn (PageComponent $component): mixed => $component->props['message'], PageComponents::find($editor, 'Shared:Notice', [])),
        );
        $modal = PageComponents::one($editor, 'Shared:Modal', ['description' => $deleting]);
        $form = PageComponents::one($modal, 'Shared:Form', ['action' => '/cms_redirects_delete?id='.self::ID]);
        self::assertSame(['Shared:FormButton'], array_map(static fn (PageComponent $child): string => $child->name, $form->children));
    }

    /**
     * @return iterable<string, array{?string, list<PageAggregate>, ?string, string}>
     */
    public static function origins(): iterable
    {
        yield 'none' => [null, [], null, 'cms.redirects.delete.description(%source%=/vieja.html)'];
        yield 'a page off the site' => [
            self::ORIGIN,
            [PageMother::unpublished(id: self::ORIGIN, title: 'Retirada', slug: 'retirada')],
            'cms.redirects.edit.origin(%origin%=cms.redirects.target.page(%name%=Retirada))',
            'cms.redirects.delete.description_origin(%origin%=cms.redirects.target.page(%name%=Retirada), %source%=/vieja.html)',
        ];
        yield 'a page deleted since' => [self::ORIGIN, [], null, 'cms.redirects.delete.description(%source%=/vieja.html)'];
    }

    /**
     * Only where the address leads changes: an address of an image leads to
     * images, any other to pages, or is gone; the field holds where it leads
     * now.
     *
     * @param list<string>          $actions
     * @param array<string, string> $values
     */
    #[DataProvider('choices')]
    public function testItLetsTheAddressLeadOnlyWhereItCan(RedirectResponse $redirect, array $actions, array $values): void
    {
        $editor = $this->edit($redirect);

        $form = PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_redirects_update?id='.self::ID]);
        $choice = PageComponents::one($form, 'Shared:FormChoice', ['name' => 'action', 'value' => $values['action']]);
        $options = $choice->props['options'];
        self::assertIsArray($options);
        self::assertSame($actions, array_column($options, 'value'));
        foreach (['Shared:ResourceField' => 'page_id', 'Shared:ImageField' => 'image_id'] as $field => $name) {
            self::assertSame(
                \in_array($name, array_keys($values), true) ? [$values[$name]] : [],
                array_map(static fn (PageComponent $component): mixed => $component->props['value'], PageComponents::find($form, $field, ['name' => $name])),
            );
        }
        self::assertCount(1, PageComponents::find($form, 'Shared:FormButton', []));
    }

    /**
     * @return iterable<string, array{RedirectResponse, list<string>, array<string, string>}>
     */
    public static function choices(): iterable
    {
        $redirect = static fn (bool $image, ?string $type, ?string $id): RedirectResponse => new RedirectResponse(
            self::ID,
            $image ? '/imagenes/faro-1200.webp' : '/vieja.html',
            $image,
            $type,
            $id,
            null,
            null,
            ClockMother::now(),
            ClockMother::now(),
        );

        yield 'an address leading to a page' => [$redirect(false, 'page', self::PAGE), ['page', 'post', 'section', 'tag', 'author', 'gone'], ['action' => 'page', 'page_id' => self::PAGE]];
        yield 'an address gone' => [$redirect(false, null, null), ['page', 'post', 'section', 'tag', 'author', 'gone'], ['action' => 'gone', 'page_id' => '']];
        yield 'an address of an image leading to one' => [$redirect(true, 'image', self::IMAGE), ['image', 'gone'], ['action' => 'image', 'image_id' => self::IMAGE]];
        yield 'an address of an image gone' => [$redirect(true, null, null), ['image', 'gone'], ['action' => 'gone', 'image_id' => '']];
    }

    /**
     * A term it leads to is picked as the section or the tag it is; one
     * deleted since, as a section, the first of them.
     */
    #[DataProvider('terms')]
    public function testItHoldsATermAsTheKindItIs(string $id, string $kind): void
    {
        $editor = $this->edit($this->redirect('term', $id), terms: [TermMother::published(id: self::TAG, taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno')]);

        PageComponents::one($editor, 'Shared:FormChoice', ['name' => 'action', 'value' => $kind]);
        PageComponents::one($editor, 'Shared:ResourceField', ['name' => $kind.'_id', 'value' => $id]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function terms(): iterable
    {
        yield 'a tag' => [self::TAG, 'tag'];
        yield 'a term deleted since' => ['0196f3a0-0000-7000-8000-0000000000d9', 'section'];
    }

    private function redirect(?string $targetType = 'page', ?string $targetId = self::PAGE, ?string $originType = null, ?string $originId = null): RedirectResponse
    {
        return new RedirectResponse(
            self::ID,
            '/vieja.html',
            false,
            $targetType,
            $targetId,
            $originType,
            $originId,
            ClockMother::now('2026-10-01 09:00:00'),
            ClockMother::now('2026-11-09 18:30:00'),
        );
    }

    /**
     * @param list<PageAggregate> $pages
     * @param list<Term>          $terms
     */
    private function edit(RedirectResponse $redirect, array $pages = [], array $terms = []): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $editor = new RedirectEditor(
            $urlGenerator,
            $translator,
            new RedirectTargetField($translator, $urlGenerator),
            $this->redirectNames($translator, $pages, terms: $terms),
        )($redirect);
        // Whatever a test asks, the editor gives every component what it takes.
        ComponentContracts::assertHonoured($editor);

        return $editor;
    }
}
