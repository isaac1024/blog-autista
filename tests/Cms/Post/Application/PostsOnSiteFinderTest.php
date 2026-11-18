<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostAddressResponse;
use Cms\Post\Application\PostOnSiteResponse;
use Cms\Post\Application\PostsOnSiteFinder;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;

final class PostsOnSiteFinderTest extends TestCase
{
    private const string DRAFT = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1c02';

    private const string OFF_SITE = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1c03';

    /**
     * The site shows the title and address a post was published with, not
     * those edited since, with what a card of it shows and the moment it was
     * first published; one never published, or taken off, is not on it,
     * and what is not an id never reaches the repository.
     */
    public function testItGivesThePostsOnTheSiteAsTheSiteServesThem(): void
    {
        $posts = [
            PostIdMother::DEFAULT_VALUE => PostMother::published(title: 'Después', publishedTitle: PostMother::TITLE),
            self::DRAFT => PostMother::create(id: self::DRAFT),
            self::OFF_SITE => PostMother::unpublished(id: self::OFF_SITE),
        ];
        $repository = self::createStub(PostRepository::class);
        $repository->method('findByIds')->willReturnCallback(static fn (array $ids): array => array_values(array_filter(array_map(static fn (mixed $id): ?Post => $id instanceof PostId ? $posts[$id->value()] ?? null : null, $ids))));

        self::assertEquals(
            [PostIdMother::DEFAULT_VALUE => new PostOnSiteResponse(PostMother::TITLE, new PostAddressResponse(TermMother::SLUG, '2026-11-07', PostMother::SLUG), PostMother::META_DESCRIPTION, PostMother::SECTION, PostMother::OPENING_IMAGE, ClockMother::now())],
            new PostsOnSiteFinder([PostIdMother::DEFAULT_VALUE, self::DRAFT, 'mi-post', self::OFF_SITE, PostIdMother::DEFAULT_VALUE])($repository),
        );
    }
}
