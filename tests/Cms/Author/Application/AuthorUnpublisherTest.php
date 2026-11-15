<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AuthorUnpublisher;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorRedirects;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorUnpublished;
use Cms\Author\Domain\AvatarStorage;
use Cms\Author\Domain\InvalidAuthorPublication;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Publishing, unpublishing, discarding and deleting an author, and what
 * each does to the files of its avatars.
 */
final class AuthorUnpublisherTest extends TestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItTakesThePublishedAuthorOffTheSiteAndTellsIt(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published());
        $repository->expects(self::once())->method('save')->with(self::equalTo(AuthorMother::unpublished()));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof AuthorUnpublished
                && 'cms.author.unpublished' === $event::eventName()
                && ['revision' => 2] === $event->toPrimitives(),
        ));
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::never())->method('delete');

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, $eventBus, $storage, self::createStub(AuthorRedirects::class));
    }

    /**
     * An author redirects lead to stays on the site until they lead elsewhere.
     */
    public function testItKeepsOnTheSiteAnAuthorRedirectsLeadTo(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published());
        $repository->expects(self::never())->method('save');
        $redirects = self::createStub(AuthorRedirects::class);
        $redirects->method('isTargeted')->willReturn(true);

        $this->expectExceptionObject(InvalidAuthorPublication::redirected());

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class), $redirects);
    }

    #[DataProvider('authorsOffTheSite')]
    public function testItRefusesToUnpublishAnAuthorOffTheSite(Author $author): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn($author);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidAuthorPublication::notPublished());

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class), self::createStub(AuthorRedirects::class));
    }

    /**
     * @return iterable<string, array{Author}>
     */
    public static function authorsOffTheSite(): iterable
    {
        yield 'a draft' => [AuthorMother::create(avatar: AuthorMother::AVATAR)];
        yield 'an unpublished author' => [AuthorMother::unpublished()];
    }

    public function testItThrowsWhenTheAuthorDoesNotExist(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(AuthorNotFound::withId(AuthorIdMother::DEFAULT_VALUE));

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class), self::createStub(AuthorRedirects::class));
    }
}
