<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorDeleter;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorContentsLinked;
use Cms\Author\Domain\AuthorImagesUsed;
use Cms\Author\Domain\AuthorPagesLinked;
use Cms\Author\Domain\AuthorRedirects;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AuthorUnpublished;
use Cms\Author\Domain\AuthorUsage;
use Cms\Author\Domain\AvatarPath;
use Cms\Author\Domain\AvatarStorage;
use Cms\Author\Domain\InvalidAuthorPublication;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Publishing, unpublishing, discarding and deleting an author, and what
 * each does to the files of its avatars.
 */
final class AuthorDeleterTest extends TestCase
{
    use ClockSensitiveTrait;

    private const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItThrowsWhenTheAuthorDoesNotExist(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(AuthorNotFound::withId(AuthorIdMother::DEFAULT_VALUE));

        new AuthorDeleter(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), self::createStub(AvatarStorage::class), self::createStub(AuthorUsage::class));
    }

    /**
     * An author deleted takes its avatars with it, and its bio stops using
     * the images it embedded.
     */
    public function testItDeletesAnAuthorOffTheSiteWithItsAvatars(): void
    {
        $author = AuthorMother::published(avatar: AuthorMother::OTHER_AVATAR, bio: RichTextMother::stored(['type' => 'image', 'id' => self::IMAGE]), publishedAvatar: AuthorMother::AVATAR);
        $author->unpublish(self::createStub(AuthorRedirects::class));
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn($author);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($author));
        $deleted = [];
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::exactly(2))->method('delete')->willReturnCallback(static function (AvatarPath $path) use (&$deleted): void {
            $deleted[] = $path->value();
        });
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::isInstanceOf(AuthorUnpublished::class),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof AuthorImagesUsed && ['image_ids' => []] === $event->toPrimitives()),
        );

        new AuthorDeleter(AuthorIdMother::DEFAULT_VALUE)($repository, $eventBus, $storage, self::createStub(AuthorUsage::class));

        self::assertEqualsCanonicalizing([AuthorMother::AVATAR, AuthorMother::OTHER_AVATAR], $deleted);
    }

    public function testItTellsTheBioOfAnAuthorDeletedLinksNoPage(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create(bio: RichTextMother::stored(['type' => 'paragraph', 'text' => [['text' => 'Más', 'marks' => ['link'], 'page_id' => '01a1b801-ce58-723d-8234-b76d9105e001']]])));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof AuthorPagesLinked && ['page_ids' => []] === $event->toPrimitives()),
        );

        new AuthorDeleter(AuthorIdMother::DEFAULT_VALUE)($repository, $eventBus, self::createStub(AvatarStorage::class), self::createStub(AuthorUsage::class));
    }

    /**
     * The posts, terms, authors and external resources the bio of an
     * author deleted linked to are freed; a bio that linked none tells
     * nothing of them.
     */
    public function testItFreesWhatTheBioOfAnAuthorDeletedLinkedTo(): void
    {
        $linking = AuthorMother::create(bio: RichTextMother::stored(['type' => 'paragraph', 'text' => [
            ['text' => 'Un post', 'marks' => ['link'], 'post_id' => '01a1b801-ce58-723d-8234-b76d9105e101'],
            ['text' => ' una etiqueta', 'marks' => ['link'], 'term_id' => '01a1b801-ce58-723d-8234-b76d9105e201'],
            ['text' => ' una autora', 'marks' => ['link'], 'author_id' => '01a1b801-ce58-723d-8234-b76d9105e301'],
            ['text' => ' un vídeo', 'marks' => ['link'], 'external_id' => '01a1b801-ce58-723d-8234-b76d9105e401'],
        ]]));

        self::assertSame(
            [AuthorContentsLinked::class => ['post_ids' => [], 'term_ids' => [], 'author_ids' => [], 'external_ids' => []]],
            self::eventsOfDeleting($linking),
        );
        self::assertSame([], self::eventsOfDeleting(AuthorMother::create()));
    }

    public function testItNeverDeletesAPublishedAuthor(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published());
        $repository->expects(self::never())->method('delete');
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::never())->method('delete');

        $this->expectExceptionObject(InvalidAuthorPublication::publishedAuthor());

        new AuthorDeleter(AuthorIdMother::DEFAULT_VALUE)($repository, self::createStub(EventBus::class), $storage, self::createStub(AuthorUsage::class));
    }

    /**
     * What deleting the author tells, each event by its class.
     *
     * @return array<class-string, array<string, mixed>>
     */
    private static function eventsOfDeleting(Author $author): array
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn($author);
        $told = [];
        $eventBus = self::createStub(EventBus::class);
        $eventBus->method('publish')->willReturnCallback(static function (DomainEvent ...$events) use (&$told): void {
            foreach ($events as $event) {
                $told[$event::class] = $event->toPrimitives();
            }
        });

        new AuthorDeleter(AuthorIdMother::DEFAULT_VALUE)($repository, $eventBus, self::createStub(AvatarStorage::class), self::createStub(AuthorUsage::class));

        return $told;
    }
}
