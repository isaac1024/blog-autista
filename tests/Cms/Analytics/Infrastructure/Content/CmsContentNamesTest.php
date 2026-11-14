<?php

declare(strict_types=1);

namespace Cms\Tests\Analytics\Infrastructure\Content;

use Cms\Analytics\Domain\ContentNames;
use Cms\Analytics\Domain\Dimension;
use Cms\Author\Domain\AuthorRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What the site counts the visits of is named as the CMS keeps it: pages
 * and posts by their titles, sections, tags and authors by their names, a
 * term told as a section or a tag. One deleted, or what is no id of one,
 * has no name; nor has one of another kind.
 */
final class CmsContentNamesTest extends KernelTestCase
{
    private const string ONE = '01a1b801-ce58-723d-8234-b76d9105f001';
    private const string OTHER = '01a1b801-ce58-723d-8234-b76d9105f002';
    private const string DELETED = '01a1b801-ce58-723d-8234-b76d9105f003';

    #[\Override]
    protected function setUp(): void
    {
        self::getContainer()->get(PageRepository::class)->save(PageMother::create(id: self::ONE, title: 'Sobre mí', slug: 'sobre-mi'));
        self::getContainer()->get(PostRepository::class)->save(PostMother::create(id: self::OTHER, title: 'Un post'));
        $terms = self::getContainer()->get(TermRepository::class);
        $terms->save(TermMother::create(id: '01a1b801-ce58-723d-8234-b76d9105f011', name: 'Día a día', slug: 'dia-a-dia'));
        $terms->save(TermMother::published(id: '01a1b801-ce58-723d-8234-b76d9105f012', taxonomy: Taxonomy::Tag, name: 'Sueño', slug: 'sueno'));
        self::getContainer()->get(AuthorRepository::class)->save(AuthorMother::create(id: '01a1b801-ce58-723d-8234-b76d9105f021', name: 'Ana', slug: 'ana'));
    }

    /**
     * @param array<string, array{name: string, kind: string}> $names
     */
    #[DataProvider('kinds')]
    public function testItNamesWhatStillExistsOfTheKind(Dimension $kind, array $names): void
    {
        self::assertEqualsCanonicalizing($names, self::getContainer()->get(ContentNames::class)->namesOf($kind, [
            self::ONE,
            self::OTHER,
            '01a1b801-ce58-723d-8234-b76d9105f011',
            '01a1b801-ce58-723d-8234-b76d9105f012',
            '01a1b801-ce58-723d-8234-b76d9105f021',
            self::DELETED,
            'sobre-mi',
        ]));
    }

    /**
     * @return iterable<string, array{Dimension, array<string, array{name: string, kind: string}>}>
     */
    public static function kinds(): iterable
    {
        yield 'the pages' => [Dimension::Page, [self::ONE => ['name' => 'Sobre mí', 'kind' => 'page']]];
        yield 'the posts' => [Dimension::Post, [self::OTHER => ['name' => 'Un post', 'kind' => 'post']]];
        yield 'the sections and tags' => [Dimension::Term, [
            '01a1b801-ce58-723d-8234-b76d9105f011' => ['name' => 'Día a día', 'kind' => 'section'],
            '01a1b801-ce58-723d-8234-b76d9105f012' => ['name' => 'Sueño', 'kind' => 'tag'],
        ]];
        yield 'the authors' => [Dimension::Author, ['01a1b801-ce58-723d-8234-b76d9105f021' => ['name' => 'Ana', 'kind' => 'author']]];
        yield 'what is no content' => [Dimension::Channel, []];
    }
}
