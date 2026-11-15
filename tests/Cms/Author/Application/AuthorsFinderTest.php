<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\TestCase;

final class AuthorsFinderTest extends TestCase
{
    private const string OTHER = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b09';

    /**
     * What is not the id of an author finds none, and stops none of the rest.
     */
    public function testItFindsTheAuthorsWithTheGivenIdsAsTheCmsShowsThem(): void
    {
        $first = AuthorMother::create();
        $second = AuthorMother::published(id: self::OTHER, slug: 'ana');
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([AuthorIdMother::create(), AuthorIdMother::create(self::OTHER)]))
            ->willReturn([$first, $second]);

        self::assertEquals(
            [AuthorResponse::fromAuthor($first), AuthorResponse::fromAuthor($second)],
            new AuthorsFinder([AuthorIdMother::DEFAULT_VALUE, 'isaac', self::OTHER])($repository),
        );
    }
}
