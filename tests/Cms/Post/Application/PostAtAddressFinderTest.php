<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostAtAddressFinder;
use Cms\Post\Application\PostResponse;
use Cms\Post\Domain\PostAddress;
use Cms\Post\Domain\PostRepository;
use Cms\Shared\Domain\Slug;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PostAtAddressFinderTest extends TestCase
{
    public function testItFindsThePostTheSiteServesAtTheAddress(): void
    {
        $post = PostMother::published();
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('findOnSiteAt')->with(self::equalTo(self::address()))->willReturn($post);

        self::assertEquals(PostResponse::fromPost($post), new PostAtAddressFinder(TermMother::SLUG, '2026-11-07', PostMother::SLUG)->onSite($repository));
    }

    public function testItFindsThePostOffTheSiteWhoseLastAddressItWas(): void
    {
        $post = PostMother::unpublished();
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())->method('findOffSiteByLastAddress')->with(self::equalTo(self::address()))->willReturn($post);

        self::assertEquals(PostResponse::fromPost($post), new PostAtAddressFinder(TermMother::SLUG, '2026-11-07', PostMother::SLUG)->lastOffSite($repository));
    }

    public function testItFindsNoneWhereNoPostIs(): void
    {
        $repository = self::createStub(PostRepository::class);

        self::assertNull(new PostAtAddressFinder(TermMother::SLUG, '2026-11-07', PostMother::SLUG)->onSite($repository));
        self::assertNull(new PostAtAddressFinder(TermMother::SLUG, '2026-11-07', PostMother::SLUG)->lastOffSite($repository));
    }

    /**
     * What can't be the address of a post finds none, without asking.
     */
    #[DataProvider('noAddresses')]
    public function testItFindsNoneAtWhatIsNoAddress(string $section, string $day, string $slug): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::never())->method('findOnSiteAt');
        $repository->expects(self::never())->method('findOffSiteByLastAddress');

        self::assertNull(new PostAtAddressFinder($section, $day, $slug)->onSite($repository));
        self::assertNull(new PostAtAddressFinder($section, $day, $slug)->lastOffSite($repository));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function noAddresses(): iterable
    {
        yield 'a day past the end of its month' => [TermMother::SLUG, '2026-02-30', PostMother::SLUG];
        yield 'no day at all' => [TermMother::SLUG, 'ayer', PostMother::SLUG];
        yield 'a section that is no slug' => ['Vida Diaria', '2026-11-07', PostMother::SLUG];
        yield 'a slug that is no slug' => [TermMother::SLUG, '2026-11-07', 'Mi Día'];
    }

    private static function address(): PostAddress
    {
        return new PostAddress(new Slug(TermMother::SLUG), new \DateTimeImmutable('2026-11-07', new \DateTimeZone(PostAddress::TIME_ZONE)), new Slug(PostMother::SLUG));
    }
}
