<?php

declare(strict_types=1);

namespace Web\Tests\Author\Application;

use PHPUnit\Framework\TestCase;
use Web\Author\Application\AuthorOnSiteFinder;
use Web\Author\Application\AuthorResponse;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Tests\Author\Domain\AuthorIdMother;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;

final class AuthorOnSiteFinderTest extends TestCase
{
    public function testItHandsOverTheAuthorWhenItIsOnTheSite(): void
    {
        $author = Author::publish(CmsAuthorPublishedMother::create());
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(AuthorIdMother::create()))
            ->willReturn($author);

        self::assertEquals(AuthorResponse::fromAuthor($author), (new AuthorOnSiteFinder(AuthorIdMother::DEFAULT_VALUE))($repository));
    }

    public function testItFindsNothingWhenTheAuthorIsNotOnTheSite(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);

        self::assertNull((new AuthorOnSiteFinder(AuthorIdMother::DEFAULT_VALUE))($repository));
    }
}
