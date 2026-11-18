<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AuthorUnpublisher;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorContentsLinked;
use Cms\Author\Domain\AuthorImagesUsed;
use Cms\Author\Domain\AuthorPagesLinked;
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
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Publishing, unpublishing, discarding and deleting an author, and what
 * each does to the files of its avatars.
 */
final class AuthorUnpublisherTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string LINKED_PAGE = '01a1b801-ce58-723d-8234-b76d9105e001';

    private const string LINKED_POST = '01a1b801-ce58-723d-8234-b76d9105f001';

    private const string EMBEDDED_IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

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

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, $eventBus, new RecordingTransaction(), $storage, self::createStub(AuthorRedirects::class));
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

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::createStub(AvatarStorage::class), $redirects);
    }

    #[DataProvider('authorsOffTheSite')]
    public function testItRefusesToUnpublishAnAuthorOffTheSite(Author $author): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn($author);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidAuthorPublication::notPublished());

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::createStub(AvatarStorage::class), self::createStub(AuthorRedirects::class));
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

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), new RecordingTransaction(), self::createStub(AvatarStorage::class), self::createStub(AuthorRedirects::class));
    }

    /**
     * Off the site, what only the bio once there used is free: the images,
     * pages and posts a draft no longer uses can be deleted.
     */
    public function testItStopsUsingWhatOnlyTheVersionOnTheSiteLinkedTo(): void
    {
        $subject = AuthorMother::published(publishedBio: ['version' => 1, 'blocks' => [['type' => 'image', 'id' => self::EMBEDDED_IMAGE], ['type' => 'paragraph', 'text' => [
            ['text' => 'una página', 'marks' => ['link'], 'page_id' => self::LINKED_PAGE],
            ['text' => ' y un post', 'marks' => ['link'], 'post_id' => self::LINKED_POST],
        ]]]]);
        $told = [];
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(static function (DomainEvent ...$events) use (&$told): void {
            $told = array_map(static fn (DomainEvent $event): array => [$event::eventName(), $event->toPrimitives()], $events);
        });
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn($subject);

        new AuthorUnpublisher(AuthorIdMother::DEFAULT_VALUE)($repository, $eventBus, new RecordingTransaction(), self::createStub(AvatarStorage::class), self::createStub(AuthorRedirects::class));

        self::assertSame([
            [AuthorContentsLinked::eventName(), ['post_ids' => [], 'term_ids' => [], 'author_ids' => [], 'external_ids' => []]],
            [AuthorImagesUsed::eventName(), ['image_ids' => []]],
            [AuthorPagesLinked::eventName(), ['page_ids' => []]],
            [AuthorUnpublished::eventName(), ['revision' => 2]],
        ], $told);
    }
}
