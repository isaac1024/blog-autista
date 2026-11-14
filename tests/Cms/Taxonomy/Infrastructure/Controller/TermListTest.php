<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsResponse;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermName;
use Cms\Taxonomy\Infrastructure\Controller\TaxonomySegment;
use Cms\Taxonomy\Infrastructure\Controller\TermAddress;
use Cms\Taxonomy\Infrastructure\Controller\TermList;
use Cms\Taxonomy\Infrastructure\Controller\TermState;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the list of the terms of a taxonomy makes: what it lists,
 * what it says when there is nothing to list, and where each term and each
 * search leads. Routes are generated as their names; the texts are their
 * translation keys, with what their placeholders are given.
 */
final class TermListTest extends TestCase
{
    private const string ID = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01';

    #[DataProvider('segments')]
    public function testItNamesTheTaxonomyListedAndCreatesATermOfItFromItsName(TaxonomySegment $segment): void
    {
        $list = $this->list($segment, [], '', 1, false);

        PageComponents::one($list, 'Shared:PageTitle', ['title' => \sprintf('cms.%s.title', $segment->value), 'subtitle' => \sprintf('cms.%s.subtitle', $segment->value)]);
        $form = PageComponents::one($list, 'Shared:Form', ['action' => '/cms_terms_create?taxonomy='.$segment->value, 'csrfTokenId' => 'cms_terms_create']);
        $name = PageComponents::one($form, 'Shared:FormInput', ['name' => 'name']);
        self::assertSame(['required' => true, 'minlength' => TermName::MIN_LENGTH, 'maxlength' => TermName::MAX_LENGTH, 'autofocus' => true], $name->props['attributes']);
        PageComponents::one($list, 'Shared:Modal', ['trigger' => \sprintf('cms.%s.add', $segment->value), 'title' => \sprintf('cms.%s.modal.title', $segment->value)]);
        PageComponents::one($form, 'Shared:FormButton', ['label' => \sprintf('cms.%s.modal.submit', $segment->value)]);
    }

    /**
     * @return iterable<string, array{TaxonomySegment}>
     */
    public static function segments(): iterable
    {
        yield 'the sections' => [TaxonomySegment::Sections];
        yield 'the tags' => [TaxonomySegment::Tags];
    }

    public function testItKeepsTheSearchInItsBox(): void
    {
        $list = $this->list(TaxonomySegment::Tags, [TermMother::create(id: self::ID, taxonomy: Taxonomy::Tag)], '  sueño ', 1, false);

        PageComponents::one($list, 'Shared:SearchForm', ['action' => '/cms_terms?taxonomy=tags', 'name' => 'name', 'value' => 'sueño']);
    }

    /**
     * With no term at all, the list invites to create the first one; with
     * none found, it tells what was searched and leads back to every term.
     */
    #[DataProvider('nothingToList')]
    public function testItTellsWhyThereIsNothingToList(string $search, string $title, string $message, string $action): void
    {
        $list = $this->list(TaxonomySegment::Sections, [], $search, 1, false);

        $empty = PageComponents::one($list, 'Shared:EmptyState', ['title' => $title, 'message' => $message]);
        self::assertSame([$action], array_map(static fn (PageComponent $child): string => $child->name, $empty->children));
        self::assertSame([], PageComponents::find($list, 'Shared:ResourceList', []));
        self::assertSame([], PageComponents::find($list, 'Shared:Pagination', []));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function nothingToList(): iterable
    {
        yield 'no term at all' => ['', 'cms.sections.empty.title', 'cms.sections.empty.message', 'Shared:ModalTrigger'];
        yield 'no term found' => [' vida ', 'cms.terms.search.empty.title', 'cms.sections.search_empty(%name%=vida)', 'Shared:ButtonLink'];
    }

    public function testItOpensTheModalToCreateTheFirstTerm(): void
    {
        $list = $this->list(TaxonomySegment::Sections, [], '', 1, false);

        $modal = PageComponents::one($list, 'Shared:Modal', []);
        PageComponents::one($list, 'Shared:ModalTrigger', ['target' => $modal->props['id'], 'label' => 'cms.sections.empty.action']);
    }

    public function testItLeadsBackToEveryTermFromASearchWithoutResults(): void
    {
        $list = $this->list(TaxonomySegment::Tags, [], 'sueño', 1, false);

        PageComponents::one($list, 'Shared:ButtonLink', ['url' => '/cms_terms?taxonomy=tags', 'label' => 'cms.tags.search_all']);
    }

    public function testItListsEachTermLeadingToItsEditor(): void
    {
        $list = $this->list(TaxonomySegment::Sections, [TermMother::published(id: self::ID, publishedName: 'Antes')], '', 1, false);

        PageComponents::one($list, 'Shared:ResourceList', ['label' => 'cms.sections.title']);
        PageComponents::one($list, 'Shared:ResourceRow', [
            'title' => TermMother::NAME,
            'url' => '/cms_terms_edit?taxonomy=sections&id='.self::ID,
            'meta' => '/vida-diaria/ · cms.terms.list.updated_at(%date%=7 nov 2026)',
            'status' => 'cms.terms.status.changed.label',
        ]);
    }

    /**
     * A term is listed at the address the site serves it at: a published
     * one at its slug there, whatever it is edited with, any other at the one
     * saved, or as having none yet.
     */
    #[DataProvider('addresses')]
    public function testItListsEachTermAtItsAddress(TaxonomySegment $segment, Term $term, string $address): void
    {
        $list = $this->list($segment, [$term], '', 1, false);

        $meta = PageComponents::one($list, 'Shared:ResourceRow', [])->props['meta'];
        self::assertIsString($meta);
        self::assertStringStartsWith($address.' · ', $meta);
    }

    /**
     * @return iterable<string, array{TaxonomySegment, Term, string}>
     */
    public static function addresses(): iterable
    {
        yield 'a published tag' => [TaxonomySegment::Tags, TermMother::published(id: self::ID, taxonomy: Taxonomy::Tag, slug: 'sueno', publishedSlug: 'dormir'), '/tag/dormir.html'];
        yield 'a section draft' => [TaxonomySegment::Sections, TermMother::create(id: self::ID, slug: 'borrador'), '/borrador/'];
        yield 'a draft without a slug' => [TaxonomySegment::Sections, TermMother::create(id: self::ID, slug: ''), 'cms.terms.list.no_slug'];
    }

    /**
     * The list goes on through the pages of its search, and the first one
     * goes without a number.
     */
    #[DataProvider('pagination')]
    public function testItLeadsThroughThePagesOfTheSearch(string $search, int $number, bool $hasMore, ?string $previous, ?string $next): void
    {
        $list = $this->list(TaxonomySegment::Tags, [TermMother::create(id: self::ID, taxonomy: Taxonomy::Tag)], $search, $number, $hasMore);

        PageComponents::one($list, 'Shared:Pagination', ['previousUrl' => $previous, 'nextUrl' => $next]);
    }

    /**
     * @return iterable<string, array{string, int, bool, ?string, ?string}>
     */
    public static function pagination(): iterable
    {
        yield 'the only page' => ['', 1, false, null, null];
        yield 'the first of many' => ['', 1, true, null, '/cms_terms?taxonomy=tags&page=2'];
        yield 'one in between' => ['', 2, true, '/cms_terms?taxonomy=tags', '/cms_terms?taxonomy=tags&page=3'];
        yield 'one of a search' => [' sueño ', 2, true, '/cms_terms?taxonomy=tags&name=sue%C3%B1o', '/cms_terms?taxonomy=tags&name=sue%C3%B1o&page=3'];
    }

    /**
     * @param list<Term> $terms
     */
    private function list(TaxonomySegment $segment, array $terms, string $search, int $number, bool $hasMore): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $list = new TermList(
            $urlGenerator,
            $translator,
            new TermState($translator),
            new TermAddress($urlGenerator),
            new ListPagination($translator, $urlGenerator),
        )($segment, new TermsResponse(array_map(TermResponse::fromTerm(...), $terms), $hasMore), $search, $number);
        // Whatever a test asks, the list gives every component what it takes.
        ComponentContracts::assertHonoured($list);

        return $list;
    }
}
