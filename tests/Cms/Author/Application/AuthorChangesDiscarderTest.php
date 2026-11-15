<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorChangesDiscarder;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarPath;
use Cms\Author\Domain\AvatarStorage;
use Cms\Author\Domain\InvalidAuthorPublication;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Publishing, unpublishing, discarding and deleting an author, and what
 * each does to the files of its avatars.
 */
final class AuthorChangesDiscarderTest extends TestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    /**
     * Discarding brings the avatar on the site back, so the one being edited
     * goes.
     */
    public function testItDiscardsTheChangesAndTheAvatarOnlyTheyHad(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published(name: 'Isaac', avatar: AuthorMother::OTHER_AVATAR, publishedName: AuthorMother::NAME, publishedAvatar: AuthorMother::AVATAR));
        $repository->expects(self::once())->method('save')->with(self::equalTo(AuthorMother::published()));
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::once())->method('delete')->with(self::equalTo(new AvatarPath(AuthorMother::OTHER_AVATAR)));

        new AuthorChangesDiscarder(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), $storage);
    }

    public function testItRefusesToDiscardWithoutChanges(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published());
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidAuthorPublication::noChangesToDiscard());

        new AuthorChangesDiscarder(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class));
    }

    public function testItThrowsWhenTheAuthorDoesNotExist(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(AuthorNotFound::withId(AuthorIdMother::DEFAULT_VALUE));

        new AuthorChangesDiscarder(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class));
    }
}
