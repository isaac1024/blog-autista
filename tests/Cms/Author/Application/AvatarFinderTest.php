<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AvatarFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\TestCase;

final class AvatarFinderTest extends TestCase
{
    /**
     * The avatar of either version: the one edited, or the one the site has.
     */
    public function testItFindsAnAvatarOfTheAuthor(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::exactly(2))->method('find')->with(self::equalTo(AuthorIdMother::create()))->willReturn(AuthorMother::published(avatar: AuthorMother::OTHER_AVATAR, publishedAvatar: AuthorMother::AVATAR));

        self::assertSame(AuthorMother::OTHER_AVATAR, new AvatarFinder(AuthorIdMother::DEFAULT_VALUE, AuthorMother::OTHER_AVATAR)($repository));
        self::assertSame(AuthorMother::AVATAR, new AvatarFinder(AuthorIdMother::DEFAULT_VALUE, AuthorMother::AVATAR)($repository));
    }

    public function testItFindsNoAvatarTheAuthorDoesNotHave(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published());

        $this->expectExceptionObject(AuthorNotFound::withId(AuthorIdMother::DEFAULT_VALUE));

        new AvatarFinder(AuthorIdMother::DEFAULT_VALUE, AuthorMother::OTHER_AVATAR)($repository);
    }

    public function testItThrowsWhenTheAuthorDoesNotExist(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(AuthorNotFound::withId(AuthorIdMother::DEFAULT_VALUE));

        new AvatarFinder(AuthorIdMother::DEFAULT_VALUE, AuthorMother::AVATAR)($repository);
    }
}
