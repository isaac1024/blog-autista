<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\LatestPostsOnSiteFinder;
use Cms\Post\Application\PostOnSiteCardResponse;
use Cms\Post\Domain\PostRepository;
use Cms\Post\Domain\PostSelection;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\TestCase;

final class LatestPostsOnSiteFinderTest extends TestCase
{
    /**
     * It asks for the latest of the selection, as many as it was given, and
     * hands over the cards of those on the site, in that order: one off it,
     * which a preview can't show as the site does, is left out.
     */
    public function testItHandsOverTheCardsOfTheLatestPostsOnTheSite(): void
    {
        $newest = PostMother::published(id: PostIdMother::random()->value(), slug: 'nuevo');
        $oldest = PostMother::published();
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('latestOnSite')->with(new PostSelection(PostMother::SECTION, null, []), 6)->willReturn([$newest, PostMother::unpublished(id: PostIdMother::random()->value()), $oldest]);

        self::assertEquals(
            [PostOnSiteCardResponse::fromPost($newest), PostOnSiteCardResponse::fromPost($oldest)],
            new LatestPostsOnSiteFinder(PostMother::SECTION, null, [], 6)($repository),
        );
    }
}
