<?php

declare(strict_types=1);

namespace Web\Tests\Author\Application;

use PHPUnit\Framework\TestCase;
use Shared\Domain\AvatarCopy;
use Web\Author\Application\AuthorSaver;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\AvatarCopies;
use Web\Author\Domain\SlugTaken;
use Web\Tests\Author\Domain\AuthorIdMother;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;

final class AuthorSaverTest extends TestCase
{
    private const string OTHER_AVATAR = '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f04.webp';

    public function testItPutsANewAuthorOnTheSiteWithTheCopiesOfItsAvatar(): void
    {
        $name = AvatarCopy::named('isaac', CmsAuthorPublishedMother::AVATAR);
        $repository = $this->createMock(AuthorRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(AuthorIdMother::create()))
            ->willReturn(null);
        $repository->method('findBySlug')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Author $author): bool => ['Isaac Abreu', 'isaac', $name] === [$author->name(), $author->slug(), $author->avatarName()]));
        $copies = $this->createMock(AvatarCopies::class);
        $copies->expects(self::once())->method('publish')->with(CmsAuthorPublishedMother::AVATAR, $name);
        $copies->expects(self::never())->method('remove');

        (new AuthorSaver(CmsAuthorPublishedMother::create()))($repository, $copies);
    }

    public function testItReplacesTheAuthorAndRemovesTheCopiesOfTheAvatarItHadOnceSaved(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(Author::publish(CmsAuthorPublishedMother::create()));
        $repository->method('findBySlug')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Author $author): bool => 'Otra persona' === $author->name()));
        $copies = $this->createMock(AvatarCopies::class);
        $copies->expects(self::once())->method('publish')->with(self::OTHER_AVATAR, AvatarCopy::named('isaac', self::OTHER_AVATAR));
        $copies->expects(self::once())->method('remove')->with(AvatarCopy::named('isaac', CmsAuthorPublishedMother::AVATAR));

        (new AuthorSaver(CmsAuthorPublishedMother::create(name: 'Otra persona', avatar: self::OTHER_AVATAR)))($repository, $copies);
    }

    public function testItKeepsTheCopiesOfAnAvatarThatDoesNotChange(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(Author::publish(CmsAuthorPublishedMother::create()));
        $repository->method('findBySlug')->willReturn(null);
        $copies = $this->createMock(AvatarCopies::class);
        $copies->expects(self::never())->method('remove');

        (new AuthorSaver(CmsAuthorPublishedMother::create(name: 'Otra persona')))($repository, $copies);
    }

    /**
     * Copies made for an author that could not be saved are left behind by
     * no one.
     */
    public function testItRemovesTheNewCopiesWhenTheAuthorCannotBeSaved(): void
    {
        $failure = new \RuntimeException('The database is gone.');
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->method('findBySlug')->willReturn(null);
        $repository->method('save')->willThrowException($failure);
        $copies = $this->createMock(AvatarCopies::class);
        $copies->expects(self::once())->method('remove')->with(AvatarCopy::named('isaac', CmsAuthorPublishedMother::AVATAR));

        $this->expectExceptionObject($failure);

        (new AuthorSaver(CmsAuthorPublishedMother::create()))($repository, $copies);
    }

    public function testItRefusesASlugAnotherAuthorStillHasWithoutCopyingAnything(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->method('findBySlug')->willReturn(Author::publish(CmsAuthorPublishedMother::create(id: AuthorIdMother::random()->value())));
        $repository->expects(self::never())->method('save');
        $copies = $this->createMock(AvatarCopies::class);
        $copies->expects(self::never())->method('publish');

        $this->expectExceptionObject(SlugTaken::withSlug('isaac'));

        (new AuthorSaver(CmsAuthorPublishedMother::create()))($repository, $copies);
    }
}
