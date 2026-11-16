<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Application;

use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Application\AuthorUpdater;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorContentsLinked;
use Cms\Author\Domain\AuthorImagesUsed;
use Cms\Author\Domain\AuthorPagesLinked;
use Cms\Author\Domain\AuthorRepository;
use Cms\Author\Domain\AvatarFile;
use Cms\Author\Domain\AvatarPath;
use Cms\Author\Domain\AvatarStorage;
use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Author\Domain\AvatarFileMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RichText\RichTextMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class AuthorUpdaterTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string BEFORE = '2026-10-01 09:00:00';

    private const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e001';

    private const string OTHER_AUTHOR = '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b03';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItSavesTheFieldsEditedKeepingTheAvatar(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create(avatar: AuthorMother::AVATAR, updatedAt: self::BEFORE));
        $repository->expects(self::once())->method('save')->with(self::equalTo(AuthorMother::create(
            name: 'Isaac',
            slug: 'isaac-a',
            avatar: AuthorMother::AVATAR,
            bio: AuthorMother::BIO,
            metaDescription: 'Quién escribe',
        )));
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::never())->method('save');
        $storage->expects(self::never())->method('delete');

        self::update($repository, $storage, name: 'Isaac', slug: 'isaac-a', bio: AuthorMother::BIO, metaDescription: ' Quién  escribe ');
    }

    public function testItKeepsTheUpdateDateWhenNothingChanged(): void
    {
        $author = AuthorMother::create(updatedAt: self::BEFORE);
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn($author);
        $repository->expects(self::once())->method('save')->with(self::equalTo(AuthorMother::create(updatedAt: self::BEFORE)));

        self::update($repository, self::createStub(AvatarStorage::class));
    }

    /**
     * The avatar uploaded is kept under a name of its own, and the one it
     * replaces, which the site doesn't have, is removed once saved.
     */
    public function testItKeepsTheNewAvatarAndRemovesTheOneItReplaces(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create(avatar: AuthorMother::AVATAR));
        $saved = null;
        $repository->expects(self::once())->method('save')->willReturnCallback(static function (Author $author) use (&$saved): void {
            $saved = $author->avatar();
        });
        $storage = $this->createMock(AvatarStorage::class);
        $kept = null;
        $storage->expects(self::once())->method('save')->willReturnCallback(static function (AvatarPath $path, AvatarFile $file) use (&$kept): void {
            self::assertSame(AvatarFileMother::path(), $file->path());
            $kept = $path;
        });
        $storage->expects(self::once())->method('delete')->with(self::equalTo(new AvatarPath(AuthorMother::AVATAR)));

        self::update($repository, $storage, avatar: AvatarFileMother::path());

        self::assertNotNull($kept);
        self::assertEquals($kept, $saved);
        self::assertNotSame(AuthorMother::AVATAR, $kept->value());
    }

    /**
     * The site serves the avatar it has until the author is published again.
     */
    public function testItKeepsTheAvatarOnTheSiteWhenANewOneIsUploaded(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::published());
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::once())->method('save');
        $storage->expects(self::never())->method('delete');

        self::update($repository, $storage, bio: AuthorMother::BIO, metaDescription: AuthorMother::META_DESCRIPTION, avatar: AvatarFileMother::path());
    }

    /**
     * When saving fails, the avatar kept for it is removed again, and the
     * one it was to replace stays: the author saved still has it.
     */
    public function testItRemovesTheNewAvatarWhenSavingFails(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create(avatar: AuthorMother::AVATAR));
        $repository->method('save')->willThrowException(new \RuntimeException('The database is gone.'));
        $storage = $this->createMock(AvatarStorage::class);
        $kept = null;
        $storage->method('save')->willReturnCallback(static function (AvatarPath $path) use (&$kept): void {
            $kept = $path;
        });
        $storage->expects(self::once())->method('delete')->willReturnCallback(static function (AvatarPath $path) use (&$kept): void {
            self::assertEquals($kept, $path);
        });

        $this->expectExceptionMessage('The database is gone.');

        self::update($repository, $storage, avatar: AvatarFileMother::path());
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidAvatars')]
    public function testItKeepsNothingWhenTheAvatarIsInvalid(string $file, string $key, array $parameters): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create());
        $repository->expects(self::never())->method('save');
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => self::update($repository, $storage, avatar: AvatarFileMother::path($file)));
    }

    /**
     * @return iterable<string, array{string, string, array<string, int|string>}>
     */
    public static function invalidAvatars(): iterable
    {
        yield 'one pixel narrower' => [AvatarFileMother::NARROWER, 'errors.author.avatar.wrong_size', ['%width%' => 400, '%height%' => 400]];
        yield 'one pixel shorter' => [AvatarFileMother::SHORTER, 'errors.author.avatar.wrong_size', ['%width%' => 400, '%height%' => 400]];
        yield 'a JPEG' => [AvatarFileMother::JPEG, 'errors.author.avatar.not_webp', []];
        yield 'a JPEG named as a WebP' => [AvatarFileMother::JPEG_NAMED_AS_WEBP, 'errors.author.avatar.not_webp', []];
        yield 'no image at all' => [AvatarFileMother::NOT_AN_IMAGE, 'errors.author.avatar.not_webp', []];
        yield 'no file' => ['missing.webp', 'errors.author.avatar.not_webp', []];
    }

    /**
     * @param array<array-key, mixed>   $bio
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidEdits')]
    public function testItKeepsNothingWhenAnEditIsInvalid(string $name, string $slug, array $bio, string $robots, string $key, array $parameters): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create());
        $repository->expects(self::never())->method('save');
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => self::update($repository, $storage, name: $name, slug: $slug, bio: $bio, robots: $robots, avatar: AvatarFileMother::path()));
    }

    /**
     * @return iterable<string, array{string, string, array<array-key, mixed>, string, string, array<string, int|string>}>
     */
    public static function invalidEdits(): iterable
    {
        yield 'name too short' => ['Al', AuthorMother::SLUG, RichTextMother::EMPTY, 'index,follow', 'errors.author.name.too_short', ['%min%' => 3]];
        yield 'malformed slug' => [AuthorMother::NAME, 'Isaac', RichTextMother::EMPTY, 'index,follow', 'errors.slug.malformed', ['%slug%' => 'Isaac']];
        yield 'a heading the bio has not' => [AuthorMother::NAME, AuthorMother::SLUG, RichTextMother::stored(
            ['type' => 'heading', 'level' => 2, 'text' => [['text' => 'Quién soy']]],
            ['type' => 'heading', 'level' => 3, 'text' => [['text' => 'Infancia']]],
            ['type' => 'heading', 'level' => 4, 'text' => [['text' => 'Colegio']]],
        ), 'index,follow', 'errors.rich_text.not_allowed', []];
        yield 'unknown robots directive' => [AuthorMother::NAME, AuthorMother::SLUG, RichTextMother::EMPTY, 'noindex', 'errors.seo.unknown_robots', ['%robots%' => 'noindex']];
        yield 'a link to the author itself' => [AuthorMother::NAME, AuthorMother::SLUG, RichTextMother::stored(
            ['type' => 'paragraph', 'text' => [['text' => 'mi página', 'marks' => ['link'], 'author_id' => AuthorIdMother::DEFAULT_VALUE]]],
        ), 'index,follow', 'errors.author.bio.links_itself', []];
        yield 'a widget of the author itself' => [AuthorMother::NAME, AuthorMother::SLUG, RichTextMother::stored(
            ['type' => 'widget', 'kind' => 'related_page', 'author_id' => AuthorIdMother::DEFAULT_VALUE],
        ), 'index,follow', 'errors.author.bio.links_itself', []];
    }

    public function testItRefusesTheSlugOfAnotherAuthor(): void
    {
        $repository = $this->createMock(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create());
        $repository->method('findBySlug')->willReturn(AuthorMother::create(id: AuthorIdMother::random()->value(), slug: 'ana'));
        $repository->expects(self::never())->method('save');
        $storage = $this->createMock(AvatarStorage::class);
        $storage->expects(self::never())->method('save');

        self::assertTells('errors.author.slug.taken', ['%slug%' => 'ana'], static fn () => self::update($repository, $storage, slug: 'ana', avatar: AvatarFileMother::path()));
    }

    /**
     * The bio tells which images it embeds and which pages it links to, so
     * neither is deleted while it uses them.
     */
    public function testItTellsTheImagesAndPagesTheBioUses(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create());
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(
            self::callback(static fn (DomainEvent $event): bool => $event instanceof AuthorImagesUsed && 'cms.author.images_used' === $event::eventName() && ['image_ids' => [self::IMAGE]] === $event->toPrimitives()),
            self::callback(static fn (DomainEvent $event): bool => $event instanceof AuthorPagesLinked && 'cms.author.pages_linked' === $event::eventName() && ['page_ids' => [self::PAGE]] === $event->toPrimitives()),
        );

        new AuthorUpdater(AuthorIdMother::DEFAULT_VALUE, AuthorMother::NAME, AuthorMother::SLUG, RichTextMother::stored(
            ['type' => 'image', 'id' => self::IMAGE],
            ['type' => 'paragraph', 'text' => [['text' => 'Contacto', 'marks' => ['link'], 'page_id' => self::PAGE]]],
        ), '', 'index,follow', null)($repository, $eventBus, self::createStub(AvatarStorage::class));
    }

    /**
     * The posts, sections and tags, and authors the bio links to are told
     * apart, so none is deleted while it links them.
     */
    public function testItTellsThePostsTermsAndAuthorsTheBioLinksTo(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(AuthorMother::create());
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof AuthorContentsLinked
                && 'cms.author.contents_linked' === $event::eventName()
                && ['post_ids' => [self::PAGE], 'term_ids' => [], 'author_ids' => [self::OTHER_AUTHOR], 'external_ids' => [self::PAGE]] === $event->toPrimitives(),
        ));

        new AuthorUpdater(AuthorIdMother::DEFAULT_VALUE, AuthorMother::NAME, AuthorMother::SLUG, RichTextMother::stored(
            ['type' => 'paragraph', 'text' => [
                ['text' => 'Mi post', 'marks' => ['link'], 'post_id' => self::PAGE],
                ['text' => ' y Ana', 'marks' => ['link'], 'author_id' => self::OTHER_AUTHOR],
                ['text' => ' y un vídeo', 'marks' => ['link'], 'external_id' => self::PAGE],
            ]],
        ), '', 'index,follow', null)($repository, $eventBus, self::createStub(AvatarStorage::class));
    }

    public function testItThrowsWhenTheAuthorDoesNotExist(): void
    {
        $repository = self::createStub(AuthorRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(AuthorNotFound::withId(AuthorIdMother::DEFAULT_VALUE));

        self::update($repository, self::createStub(AvatarStorage::class));
    }

    /**
     * @param array<array-key, mixed> $bio
     */
    private static function update(
        AuthorRepository $repository,
        AvatarStorage $storage,
        string $name = AuthorMother::NAME,
        string $slug = AuthorMother::SLUG,
        array $bio = RichTextMother::EMPTY,
        string $metaDescription = '',
        string $robots = 'index,follow',
        ?string $avatar = null,
    ): void {
        new AuthorUpdater(AuthorIdMother::DEFAULT_VALUE, $name, $slug, $bio, $metaDescription, $robots, $avatar)($repository, self::createStub(EventBus::class), $storage);
    }
}
