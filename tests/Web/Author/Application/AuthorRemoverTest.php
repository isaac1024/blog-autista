<?php

declare(strict_types=1);

namespace Web\Tests\Author\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\AvatarCopy;
use Web\Author\Application\AuthorRemover;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\AvatarCopies;
use Web\Tests\Author\Domain\AuthorIdMother;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;
use Web\Tests\Author\Domain\CmsAuthorUnpublishedMother;

final class AuthorRemoverTest extends TestCase
{
    public function testItTakesTheAuthorOffTheSiteWithTheCopiesOfItsAvatar(): void
    {
        $author = Author::publish(CmsAuthorPublishedMother::create());
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(AuthorIdMother::create()))
            ->willReturn($author);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($author));
        $copies = $this->createMock(AvatarCopies::class);
        $copies->expects(self::once())->method('remove')->with(AvatarCopy::named('isaac', CmsAuthorPublishedMother::AVATAR));

        (new AuthorRemover(CmsAuthorUnpublishedMother::create()))($repository, $copies);
    }

    public function testItDoesNothingWhenTheAuthorIsNotOnTheSite(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('delete');
        $copies = $this->createMock(AvatarCopies::class);
        $copies->expects(self::never())->method('remove');

        (new AuthorRemover(CmsAuthorUnpublishedMother::create()))($repository, $copies);
    }
}
