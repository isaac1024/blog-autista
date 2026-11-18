<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Application;

use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Application\PostsLeftBehindCounter;
use Cms\Taxonomy\Domain\PostsUnderSectionSlugs;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Taxonomy\Domain\TermIdMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Publishing a section with another slug leaves its posts on the site at
 * the address of the slug it has there now, its own and no other's; nothing
 * is left behind when the slug doesn't change, nor by a tag.
 */
final class PostsLeftBehindCounterTest extends TestCase
{
    public function testItCountsNoneWhenOnlyPostsOfAnotherSectionAreLeftThere(): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn(TermMother::published(slug: 'ahora', publishedSlug: 'antes'));
        $posts = self::createStub(PostsUnderSectionSlugs::class);
        $posts->method('countedBySection')->willReturn(['01a1b801-ce58-723d-8234-b76d9105f0aa' => 2]);

        self::assertSame(0, new PostsLeftBehindCounter('section', TermIdMother::DEFAULT_VALUE)($repository, $posts));
    }

    #[DataProvider('terms')]
    public function testItCountsThePostsOfTheSectionLeftAtItsAddressOfNow(Term $term, int $leftBehind): void
    {
        $repository = self::createStub(TermRepository::class);
        $repository->method('find')->willReturn($term);
        $asked = [];
        $posts = self::createStub(PostsUnderSectionSlugs::class);
        $posts->method('countedBySection')->willReturnCallback(static function (Slug $slug) use (&$asked): array {
            $asked[] = $slug->value();

            return [TermIdMother::DEFAULT_VALUE => 3, '01a1b801-ce58-723d-8234-b76d9105f0aa' => 2];
        });

        self::assertSame($leftBehind, new PostsLeftBehindCounter($term->taxonomy()->value, TermIdMother::DEFAULT_VALUE)($repository, $posts));
        self::assertSame(0 === $leftBehind ? [] : ['antes'], $asked);
    }

    /**
     * @return iterable<string, array{Term, int}>
     */
    public static function terms(): iterable
    {
        yield 'a section on the site with another slug' => [TermMother::published(slug: 'ahora', publishedSlug: 'antes'), 3];
        yield 'a section whose slug does not change' => [TermMother::published(), 0];
        yield 'a section never on the site' => [TermMother::create(), 0];
        yield 'a tag with another slug' => [TermMother::published(taxonomy: Taxonomy::Tag, slug: 'ahora', publishedSlug: 'antes'), 0];
    }
}
