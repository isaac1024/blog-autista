<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorOnSiteFinder;
use Cms\Author\Application\AuthorOnSiteResponse;
use Cms\Author\Domain\AuthorRepository;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthorOnSiteFinderTest extends TestCase
{
    /**
     * The site shows the name an author was published with, not one edited
     * since.
     */
    public function testItGivesTheAuthorOnTheSiteAsTheSiteServesIt(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(AuthorIdMother::create()))
            ->willReturn(AuthorMother::published(name: 'Otro nombre', publishedName: AuthorMother::NAME));

        self::assertEquals(new AuthorOnSiteResponse(AuthorMother::NAME, AuthorMother::SLUG), new AuthorOnSiteFinder(AuthorIdMother::DEFAULT_VALUE)($repository));
    }

    #[DataProvider('offTheSite')]
    public function testItFindsNoneOffTheSite(?callable $author): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null === $author ? null : $author());

        self::assertNull(new AuthorOnSiteFinder(AuthorIdMother::DEFAULT_VALUE)($repository));
    }

    /**
     * @return iterable<string, array{?callable}>
     */
    public static function offTheSite(): iterable
    {
        yield 'never published' => [static fn () => AuthorMother::create()];
        yield 'taken off' => [static fn () => AuthorMother::unpublished()];
        yield 'none at all' => [null];
    }
}
