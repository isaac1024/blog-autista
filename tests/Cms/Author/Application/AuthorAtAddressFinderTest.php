<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorAtAddressFinder;
use Cms\Author\Application\AuthorResponse;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Shared\Domain\SlugMother;
use PHPUnit\Framework\TestCase;

final class AuthorAtAddressFinderTest extends TestCase
{
    public function testItFindsTheAuthorTheSiteServesAtTheAddress(): void
    {
        $author = AuthorMother::published();
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())->method('findOnSiteBySlug')->with(self::equalTo(SlugMother::create(AuthorMother::SLUG)))->willReturn($author);

        self::assertEquals(AuthorResponse::fromAuthor($author), new AuthorAtAddressFinder(AuthorMother::SLUG)->onSite($repository));
    }

    public function testItFindsTheAuthorOffTheSiteWhoseLastAddressItWas(): void
    {
        $author = AuthorMother::unpublished();
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())->method('findOffSiteByLastSiteSlug')->with(self::equalTo(SlugMother::create(AuthorMother::SLUG)))->willReturn($author);

        self::assertEquals(AuthorResponse::fromAuthor($author), new AuthorAtAddressFinder(AuthorMother::SLUG)->lastOffSite($repository));
    }

    public function testItFindsNoneWhereNoAuthorIs(): void
    {
        $repository = self::createStub(AuthorRepository::class);

        self::assertNull(new AuthorAtAddressFinder(AuthorMother::SLUG)->onSite($repository));
        self::assertNull(new AuthorAtAddressFinder(AuthorMother::SLUG)->lastOffSite($repository));
    }

    public function testItFindsNoneAtWhatIsNoAddress(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::never())->method('findOnSiteBySlug');
        $repository->expects(self::never())->method('findOffSiteByLastSiteSlug');

        self::assertNull(new AuthorAtAddressFinder('Isaac Abreu')->onSite($repository));
        self::assertNull(new AuthorAtAddressFinder('Isaac Abreu')->lastOffSite($repository));
    }
}
