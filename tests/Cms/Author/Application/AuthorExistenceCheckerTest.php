<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorExistenceChecker;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\TestCase;

final class AuthorExistenceCheckerTest extends TestCase
{
    public function testItTellsWhetherTheAuthorExists(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::exactly(2))
            ->method('find')
            ->with(self::equalTo(AuthorIdMother::create()))
            ->willReturnOnConsecutiveCalls(AuthorMother::create(), null);

        self::assertTrue(new AuthorExistenceChecker(AuthorIdMother::DEFAULT_VALUE)($repository));
        self::assertFalse(new AuthorExistenceChecker(AuthorIdMother::DEFAULT_VALUE)($repository));
    }
}
