<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AuthorPublisher;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorPublished;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarPath;
use Cms\Author\Domain\AvatarStorage;
use Cms\Author\Domain\InvalidAuthorPublication;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Shared\Domain\SlugMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Publishing, unpublishing, discarding and deleting an author, and what
 * each does to the files of its avatars.
 */
final class AuthorPublisherTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('publishableAuthors')]
    public function testItPutsTheSavedVersionOnTheSite(Author $author, Author $expected): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn($author);
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        new AuthorPublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class));
    }

    /**
     * @return iterable<string, array{Author, Author}>
     */
    public static function publishableAuthors(): iterable
    {
        yield 'a draft' => [AuthorMother::publishable(), AuthorMother::published()];
        yield 'a published author with changes, keeping its first publication' => [
            AuthorMother::published(name: 'Isaac', publishedName: AuthorMother::NAME, firstPublishedAt: self::BEFORE, lastPublishedAt: self::BEFORE),
            AuthorMother::published(name: 'Isaac', firstPublishedAt: self::BEFORE, publicationRevision: 2),
        ];
        yield 'an unpublished author' => [AuthorMother::unpublished(), AuthorMother::published(publicationRevision: 3)];
    }

    public function testItTellsTheSiteWhatToServe(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published(avatar: AuthorMother::OTHER_AVATAR, publishedAvatar: AuthorMother::AVATAR, firstPublishedAt: self::BEFORE));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof AuthorPublished
                && 'cms.author.published' === $event::eventName()
                && AuthorIdMother::DEFAULT_VALUE === $event->aggregateId()
                && [
                    'name' => AuthorMother::NAME,
                    'slug' => AuthorMother::SLUG,
                    'avatar' => AuthorMother::OTHER_AVATAR,
                    'bio' => AuthorMother::BIO,
                    'meta_description' => AuthorMother::META_DESCRIPTION,
                    'robots' => 'index,follow',
                    'first_published_at' => ClockMother::now(self::BEFORE)->format(\DATE_ATOM),
                    'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
                    'revision' => 2,
                ] === $event->toPrimitives(),
        ));

        new AuthorPublisher(AuthorIdMother::DEFAULT_VALUE)($repository, $eventBus, self::createStub(AvatarStorage::class));
    }

    /**
     * Once the site has the new avatar, the one it had is left by both
     * versions, so its file goes.
     */
    public function testItRemovesTheAvatarTheSiteNoLongerHas(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published(avatar: AuthorMother::OTHER_AVATAR, publishedAvatar: AuthorMother::AVATAR));
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::once())->method('delete')->with(self::equalTo(new AvatarPath(AuthorMother::AVATAR)));

        new AuthorPublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), $storage);
    }

    #[DataProvider('unpublishableAuthors')]
    public function testItPublishesNothingWithoutEverythingTheSiteShows(Author $author, DomainError $error): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn($author);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject($error);

        new AuthorPublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class));
    }

    /**
     * @return iterable<string, array{Author, DomainError}>
     */
    public static function unpublishableAuthors(): iterable
    {
        yield 'without a slug' => [AuthorMother::create(slug: '', avatar: AuthorMother::AVATAR, bio: AuthorMother::BIO, metaDescription: AuthorMother::META_DESCRIPTION), InvalidAuthorPublication::withoutSlug()];
        yield 'without an avatar' => [AuthorMother::create(bio: AuthorMother::BIO, metaDescription: AuthorMother::META_DESCRIPTION), InvalidAuthorPublication::withoutAvatar()];
        yield 'without a bio' => [AuthorMother::create(avatar: AuthorMother::AVATAR, metaDescription: AuthorMother::META_DESCRIPTION), InvalidAuthorPublication::withoutBio()];
        yield 'without a meta description' => [AuthorMother::create(avatar: AuthorMother::AVATAR, bio: AuthorMother::BIO), InvalidAuthorPublication::withoutMetaDescription()];
        yield 'a published author as it is on the site' => [AuthorMother::published(), InvalidAuthorPublication::nothingToPublish()];
    }

    /**
     * Published at the address an author off the site last had, it takes it
     * from that one, which is saved without it.
     */
    public function testItTakesTheLastAddressOfTheAuthorOffTheSiteThatHadIt(): void
    {
        $former = AuthorMother::unpublished(id: AuthorIdMother::random()->value());
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::publishable());
        $repository->expects(self::once())->method('findOffSiteByLastSiteSlug')->with(self::equalTo(SlugMother::create(AuthorMother::SLUG)))->willReturn($former);
        $repository->expects(self::exactly(2))->method('save');

        new AuthorPublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class));

        self::assertNull($former->lastSiteSlug());
    }

    public function testItKeepsItsOwnLastAddress(): void
    {
        $author = AuthorMother::published(publishedName: 'Antes');
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn($author);
        $repository->method('findOffSiteByLastSiteSlug')->willReturn($author);
        $repository->expects(self::once())->method('save');

        new AuthorPublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class));

        self::assertSame(AuthorMother::SLUG, $author->lastSiteSlug()?->value());
    }

    public function testItThrowsWhenTheAuthorDoesNotExist(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(AuthorNotFound::withId(AuthorIdMother::DEFAULT_VALUE));

        new AuthorPublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class));
    }
}
