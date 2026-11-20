<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostAtAddressFinder;
use Cms\Post\Application\PostResponse;
use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostRepository;
use Cms\Shared\Domain\Slug;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\SiteTimeZone;

final class PostAtAddressFinderTest extends TestCase
{
    public function testItFindsThePostTheSiteServesAtTheAddress(): void
    {
        $post = PostMother::published();
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('findOnSiteAt')->with(self::equalTo(self::address()))->willReturn($post);

        self::assertEquals(PostResponse::fromPost($post), new PostAtAddressFinder('2026-11-07', PostMother::SLUG)->onSite($repository));
    }

    public function testItFindsThePostOffTheSiteWhoseLastAddressItWas(): void
    {
        $post = PostMother::unpublished();
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('findOffSiteByLastAddress')->with(self::equalTo(self::address()))->willReturn($post);

        self::assertEquals(PostResponse::fromPost($post), new PostAtAddressFinder('2026-11-07', PostMother::SLUG)->lastOffSite($repository));
    }

    public function testItFindsNoneWhereNoPostIs(): void
    {
        $repository = self::createStub(PostRepository::class);

        self::assertNull(new PostAtAddressFinder('2026-11-07', PostMother::SLUG)->onSite($repository));
        self::assertNull(new PostAtAddressFinder('2026-11-07', PostMother::SLUG)->lastOffSite($repository));
    }

    /**
     * What can't be the address of a post finds none, without asking.
     */
    #[DataProvider('noAddresses')]
    public function testItFindsNoneAtWhatIsNoAddress(string $day, string $slug): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::never())->method('findOnSiteAt');
        $repository->expects(self::never())->method('findOffSiteByLastAddress');

        self::assertNull(new PostAtAddressFinder($day, $slug)->onSite($repository));
        self::assertNull(new PostAtAddressFinder($day, $slug)->lastOffSite($repository));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function noAddresses(): iterable
    {
        yield 'a day past the end of its month' => ['2026-02-30', PostMother::SLUG];
        yield 'no day at all' => ['ayer', PostMother::SLUG];
        yield 'a slug that is no slug' => ['2026-11-07', 'Mi Día'];
    }

    private static function address(): PostAddress
    {
        return new PostAddress(new \DateTimeImmutable('2026-11-07', SiteTimeZone::zone()), new Slug(PostMother::SLUG));
    }
}
