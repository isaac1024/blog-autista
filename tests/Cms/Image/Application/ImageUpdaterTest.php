<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageNotFound;
use Cms\Image\Application\ImageUpdater;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageContentsLinked;
use Cms\Image\Domain\ImagePagesLinked;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ImageUpdaterTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    /**
     * When the images edited here were uploaded and last changed.
     */
    private const string BEFORE = '2026-10-01 09:00:00';

    private const string LINKED = '01a1b801-ce58-723d-8234-b76d9105e001';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the images compared with.
        self::mockTime(ClockMother::now());
    }

    /**
     * @param array<array-key, mixed>  $caption  in the stored rich text format
     * @param ?array<array-key, mixed> $expected the caption kept; null for none
     */
    #[DataProvider('validEdits')]
    public function testItSavesTheEditedTextsAndWhenTheyChanged(string $alt, array $caption, string $expectedAlt, ?array $expected): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, Text::stored(Text::paragraph('Pie anterior.'))));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(ImageMother::create(
                id: $id,
                alt: $expectedAlt,
                caption: $expected,
                createdAt: self::BEFORE,
                updatedAt: ClockMother::NOW,
            )));

        (new ImageUpdater($id, $alt, $caption))($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, ?array<array-key, mixed>}>
     */
    public static function validEdits(): iterable
    {
        $caption = Text::stored(Text::paragraph('Ilustración de archivo.'));

        yield 'new texts' => ['Círculo naranja sobre fondo azul', $caption, 'Círculo naranja sobre fondo azul', $caption];
        yield 'alternative text with its whitespace collapsed' => ["  Círculo \t naranja  ", $caption, 'Círculo naranja', $caption];
        yield 'caption left empty' => [ImageMother::ALT, Text::EMPTY, ImageMother::ALT, null];
        yield 'caption in runs' => [
            ImageMother::ALT,
            Text::stored(Text::paragraph('Ilustración ', 'de archivo.')),
            ImageMother::ALT,
            Text::stored(Text::paragraph('Ilustración ', 'de archivo.')),
        ];
        yield 'longest caption' => [ImageMother::ALT, Text::stored(Text::paragraph(str_repeat('a', 200))), ImageMother::ALT, Text::stored(Text::paragraph(str_repeat('a', 200)))];

        // Same length in characters, but 400 bytes: the limit counts characters.
        yield 'longest caption with multibyte characters' => [
            ImageMother::ALT,
            Text::stored(Text::paragraph(str_repeat('á', 100), str_repeat('é', 100))),
            ImageMother::ALT,
            Text::stored(Text::paragraph(str_repeat('á', 100), str_repeat('é', 100))),
        ];
    }

    /**
     * @param array<array-key, mixed> $caption in the stored rich text format
     */
    #[DataProvider('unchangedEdits')]
    public function testItKeepsWhenTheImageLastChangedWhenNothingChanges(string $alt, array $caption): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $stored = Text::stored(Text::paragraph('Ilustración de archivo.'));
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, $stored));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(self::image($id, $stored)));

        (new ImageUpdater($id, $alt, $caption))($repository, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>}>
     */
    public static function unchangedEdits(): iterable
    {
        $caption = Text::stored(Text::paragraph('Ilustración de archivo.'));

        yield 'the same texts' => [ImageMother::ALT, $caption];
        yield 'the same alternative text with other whitespace' => ['  '.ImageMother::ALT.'  ', $caption];
    }

    public function testItKeepsWhenAnImageWithoutACaptionLastChangedWhenNothingChanges(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, null));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(self::image($id, null)));

        (new ImageUpdater($id, ImageMother::ALT, Text::EMPTY))($repository, self::createStub(EventBus::class));
    }

    public function testItMovesWhenTheImageLastChangedWhenOnlyTheCaptionChanges(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $caption = Text::stored(Text::paragraph('Otro pie.'));
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, Text::stored(Text::paragraph('Pie anterior.'))));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(ImageMother::create(id: $id, caption: $caption, createdAt: self::BEFORE, updatedAt: ClockMother::NOW)));

        (new ImageUpdater($id, ImageMother::ALT, $caption))($repository, self::createStub(EventBus::class));
    }

    public function testItMovesWhenTheImageLastChangedWhenOnlyTheAlternativeTextChanges(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, null));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(ImageMother::create(id: $id, alt: 'Otro texto', createdAt: self::BEFORE, updatedAt: ClockMother::NOW)));

        (new ImageUpdater($id, 'Otro texto', Text::EMPTY))($repository, self::createStub(EventBus::class));
    }

    /**
     * What the error tells is spelt out, not built by the code that throws
     * it, so a test catches its key or parameters changing.
     *
     * @param array<array-key, mixed>   $caption    in the stored rich text format
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidEdits')]
    public function testItSavesNothingWhenTheEditIsInvalid(string $alt, array $caption, string $key, array $parameters): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, null));
        $repository->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => (new ImageUpdater($id, $alt, $caption))($repository, self::createStub(EventBus::class)));
    }

    /**
     * @return iterable<string, array{string, array<array-key, mixed>, string, array<string, int|string>}>
     */
    public static function invalidEdits(): iterable
    {
        $alt = ImageMother::ALT;
        $caption = Text::stored(Text::paragraph('Ilustración de archivo.'));

        yield 'blank alternative text' => [" \t ", $caption, 'errors.image.alt.empty', []];
        yield 'alternative text one character too long' => [str_repeat('a', 126), $caption, 'errors.image.alt.too_long', ['%max%' => 125]];
        yield 'caption one character too long' => [$alt, Text::stored(Text::paragraph(str_repeat('a', 201))), 'errors.image.caption.too_long', ['%max%' => 200]];
        yield 'caption too long only in its runs together' => [
            $alt,
            Text::stored(Text::paragraph(str_repeat('a', 100), str_repeat('b', 101))),
            'errors.image.caption.too_long',
            ['%max%' => 200],
        ];
        yield 'caption of two paragraphs' => [$alt, Text::stored(Text::paragraph('Uno.'), Text::paragraph('Dos.')), 'errors.image.caption.not_a_single_paragraph', []];
        yield 'caption with bold text' => [$alt, Text::stored(Text::paragraph('**Negrita**')), 'errors.rich_text.not_allowed', []];
        yield 'caption with a heading' => [$alt, Text::stored(Text::heading(2, 'Título')), 'errors.rich_text.not_allowed', []];
        yield 'caption with a list' => [$alt, Text::stored(['type' => 'list', 'style' => 'unordered', 'items' => [Text::text('Uno')]]), 'errors.rich_text.not_allowed', []];
        yield 'caption in an unknown format' => [$alt, ['version' => 2, 'blocks' => []], 'errors.rich_text.unsupported_version', []];
    }

    /**
     * A caption that links to other pages than before tells which it links
     * to now, at once.
     */
    public function testItTellsThePagesTheCaptionLinksToOnceTheyChange(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, Text::stored(Text::paragraph('Pie anterior.'))));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof ImagePagesLinked
                && $id === $event->aggregateId()
                && ['page_ids' => [self::LINKED]] === $event->toPrimitives(),
        ));

        (new ImageUpdater($id, ImageMother::ALT, Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Contacto', 'marks' => ['link'], 'page_id' => self::LINKED]]])))($repository, $eventBus);
    }

    /**
     * The posts, sections and tags, and authors a caption links to are told
     * apart from its pages.
     */
    public function testItTellsThePostsTermsAndAuthorsTheCaptionLinksTo(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, Text::stored(Text::paragraph('Pie anterior.'))));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with(self::callback(
            static fn (DomainEvent $event): bool => $event instanceof ImageContentsLinked
                && 'cms.image.contents_linked' === $event::eventName()
                && ['post_ids' => [], 'term_ids' => [self::LINKED], 'author_ids' => []] === $event->toPrimitives(),
        ));

        (new ImageUpdater($id, ImageMother::ALT, Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Sueño', 'marks' => ['link'], 'term_id' => self::LINKED]]])))($repository, $eventBus);
    }

    public function testItTellsNothingWhenTheCaptionLinksToTheSamePages(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = self::createStub(ImageRepository::class);
        $repository->method('find')->willReturn(self::image($id, Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Contacto', 'marks' => ['link'], 'page_id' => self::LINKED]]])));
        $eventBus = $this->createMock(EventBus::class);
        $eventBus->expects(self::once())->method('publish')->with();

        (new ImageUpdater($id, 'Otro texto alternativo', Text::stored(['type' => 'paragraph', 'text' => [['text' => 'Contacto', 'marks' => ['link'], 'page_id' => self::LINKED]]])))($repository, $eventBus);
    }

    public function testItFailsWhenTheImageDoesNotExist(): void
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $repository = $this->createMock(ImageRepository::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(ImageNotFound::withId($id));

        (new ImageUpdater($id, ImageMother::ALT, Text::EMPTY))($repository, self::createStub(EventBus::class));
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::never())->method('find');
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(InvalidUuid::withValue('image-id'));

        (new ImageUpdater('image-id', ImageMother::ALT, Text::EMPTY))($repository, self::createStub(EventBus::class));
    }

    /**
     * An image uploaded and last changed before, with the default text.
     *
     * @param ?array<array-key, mixed> $caption
     */
    private static function image(string $id, ?array $caption): Image
    {
        return ImageMother::create(id: $id, caption: $caption, createdAt: self::BEFORE);
    }
}
