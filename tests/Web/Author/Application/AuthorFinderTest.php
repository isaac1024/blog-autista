<?php

declare(strict_types=1);

namespace Web\Tests\Author\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\AvatarCopy;
use Shared\Domain\RichText\RichText;
use Web\Author\Application\AuthorFinder;
use Web\Author\Application\AuthorNotFound;
use Web\Author\Application\AuthorResponse;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Shared\Domain\Robots;
use Web\Tests\Author\Domain\AuthorIdMother;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;

final class AuthorFinderTest extends TestCase
{
    public function testItHandsOverTheAuthorAtTheSlug(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())
            ->method('findBySlug')
            ->with('isaac')
            ->willReturn(Author::publish(CmsAuthorPublishedMother::create(robots: Robots::NoindexFollow)));

        self::assertEquals(
            new AuthorResponse(
                AuthorIdMother::DEFAULT_VALUE,
                'Isaac Abreu',
                'isaac',
                AvatarCopy::named('isaac', CmsAuthorPublishedMother::AVATAR),
                RichText::fromArray(CmsAuthorPublishedMother::BIO),
                'Quién escribe este blog.',
                'noindex,follow',
            ),
            (new AuthorFinder('isaac'))($repository),
        );
    }

    public function testItThrowsWhenNoAuthorIsAtTheSlug(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('findBySlug')->willReturn(null);

        $this->expectExceptionObject(AuthorNotFound::withSlug('isaac'));

        (new AuthorFinder('isaac'))($repository);
    }
}
