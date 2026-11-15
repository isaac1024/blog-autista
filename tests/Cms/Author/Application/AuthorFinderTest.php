<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorFinder;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AuthorResponse;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;

final class AuthorFinderTest extends TestCase
{
    public function testItFindsTheAuthorWithTheGivenIdAsTheCmsShowsIt(): void
    {
        $author = AuthorMother::published(name: 'Otro nombre', publishedName: AuthorMother::NAME);
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(AuthorIdMother::create()))
            ->willReturn($author);

        $found = new AuthorFinder(AuthorIdMother::DEFAULT_VALUE)($repository);

        self::assertEquals(AuthorResponse::fromAuthor($author), $found);
        self::assertSame(['Otro nombre', 'published', true], [$found->name, $found->status, $found->changed]);
    }

    public function testItThrowsWhenTheAuthorDoesNotExist(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(AuthorNotFound::withId(AuthorIdMother::DEFAULT_VALUE));

        new AuthorFinder(AuthorIdMother::DEFAULT_VALUE)($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidUuid::withValue('isaac'));

        new AuthorFinder('isaac')($repository);
    }
}
