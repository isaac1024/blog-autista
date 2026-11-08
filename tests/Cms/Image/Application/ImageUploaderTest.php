<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Application;

use Cms\Image\Application\ImageUploader;
use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImagePath;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Domain\ImageStorage;
use Cms\Image\Domain\InvalidAlt;
use Cms\Image\Domain\InvalidImageFile;
use Cms\Tests\Image\Domain\ImageFileMother;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class ImageUploaderTest extends TestCase
{
    use ClockSensitiveTrait;

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the images compared with.
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('validAlts')]
    public function testItKeepsTheFileAndSavesTheImage(string $alt, Image $expected): void
    {
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::once())
            ->method('save')
            ->with(
                self::equalTo(new ImagePath('2026/11/'.ImageIdMother::DEFAULT_VALUE.'.webp')),
                self::equalTo(ImageFileMother::create()),
            );
        $storage->expects(self::never())->method('delete');
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo($expected));

        (new ImageUploader(ImageIdMother::DEFAULT_VALUE, $alt, ImageFileMother::path()))($repository, $storage, self::createStub(EventBus::class));
    }

    /**
     * @return iterable<string, array{string, Image}>
     */
    public static function validAlts(): iterable
    {
        yield 'plain text' => [ImageMother::ALT, ImageMother::create(alt: ImageMother::ALT)];
        yield 'whitespace is collapsed and trimmed' => [
            "  Cuadrado \t naranja\n sobre fondo azul  ",
            ImageMother::create(alt: 'Cuadrado naranja sobre fondo azul'),
        ];
        yield 'shortest text' => ['a', ImageMother::create(alt: 'a')];
        yield 'longest text' => [str_repeat('a', 125), ImageMother::create(alt: str_repeat('a', 125))];

        // Same length in characters, but 250 bytes: the limit counts characters.
        yield 'longest text with multibyte characters' => [str_repeat('á', 125), ImageMother::create(alt: str_repeat('á', 125))];
    }

    /**
     * Months take two digits, so they sort as text.
     */
    public function testItKeepsTheFileUnderTheYearAndTheMonthItIsUploaded(): void
    {
        self::mockTime(ClockMother::now('2027-01-31 23:59:59'));
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::once())
            ->method('save')
            ->with(self::equalTo(new ImagePath('2027/01/'.ImageIdMother::DEFAULT_VALUE.'.webp')));
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(ImageMother::create(createdAt: '2027-01-31 23:59:59')));

        (new ImageUploader(ImageIdMother::DEFAULT_VALUE, ImageMother::ALT, ImageFileMother::path()))($repository, $storage, self::createStub(EventBus::class));
    }

    /**
     * What the error tells is spelt out, not built by the code that throws
     * it, so a test catches its message, key or parameters changing.
     *
     * @param class-string<DomainError> $error
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('invalidInputs')]
    public function testItKeepsAndSavesNothingWhenTheInputIsInvalid(
        string $id,
        string $alt,
        string $file,
        string $error,
        string $message,
        string $key,
        array $parameters,
    ): void {
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::never())->method('save');
        $storage->expects(self::never())->method('delete');
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::never())->method('save');

        try {
            (new ImageUploader($id, $alt, $file))($repository, $storage, self::createStub(EventBus::class));
        } catch (DomainError $thrown) {
            self::assertSame(
                [$error, $message, 0, $key, $parameters],
                [$thrown::class, $thrown->getMessage(), $thrown->getCode(), $thrown->key(), $thrown->parameters()],
            );

            return;
        }

        self::fail(\sprintf('No %s was thrown.', $error));
    }

    /**
     * @return iterable<string, array{string, string, string, class-string<DomainError>, string, string, array<string, int|string>}>
     */
    public static function invalidInputs(): iterable
    {
        $id = ImageIdMother::DEFAULT_VALUE;
        $alt = ImageMother::ALT;
        $file = ImageFileMother::path();
        $empty = [InvalidAlt::class, 'The alternative text of an image must not be empty.', 'errors.image.alt.empty', []];
        $notWebp = [InvalidImageFile::class, 'The file of an image must be a WebP image.', 'errors.image.file.not_webp', []];
        $wrongSize = [
            InvalidImageFile::class,
            'The file of an image must be 1200 by 675 pixels.',
            'errors.image.file.wrong_size',
            ['%width%' => 1200, '%height%' => 675],
        ];

        yield 'id is not a UUID' => ['image-id', $alt, $file, InvalidUuid::class, '"image-id" is not a valid UUID.', 'errors.uuid.invalid', ['%value%' => 'image-id']];
        yield 'empty text' => [$id, '', $file, ...$empty];
        yield 'blank text' => [$id, " \t\n ", $file, ...$empty];
        yield 'text one character too long' => [
            $id,
            str_repeat('a', 126),
            $file,
            InvalidAlt::class,
            'The alternative text of an image must not exceed 125 characters.',
            'errors.image.alt.too_long',
            ['%max%' => 125],
        ];
        yield 'no file' => [$id, $alt, '', InvalidImageFile::class, 'An image needs its file.', 'errors.image.file.missing', []];
        yield 'file that is not there' => [$id, $alt, ImageFileMother::path('missing.webp'), ...$notWebp];
        yield 'a directory' => [$id, $alt, \dirname($file), ...$notWebp];
        yield 'not an image' => [$id, $alt, ImageFileMother::path(ImageFileMother::NOT_AN_IMAGE), ...$notWebp];
        yield 'a JPEG' => [$id, $alt, ImageFileMother::path(ImageFileMother::JPEG), ...$notWebp];
        yield 'a JPEG named as a WebP' => [$id, $alt, ImageFileMother::path(ImageFileMother::JPEG_NAMED_AS_WEBP), ...$notWebp];
        yield 'larger image' => [$id, $alt, ImageFileMother::path(ImageFileMother::LARGER), ...$wrongSize];
        yield 'one pixel narrower' => [$id, $alt, ImageFileMother::path(ImageFileMother::NARROWER), ...$wrongSize];
        yield 'one pixel shorter' => [$id, $alt, ImageFileMother::path(ImageFileMother::SHORTER), ...$wrongSize];
    }

    public function testItRemovesTheFileKeptWhenTheImageCannotBeSaved(): void
    {
        $failure = new \RuntimeException('The database is down.');
        $path = new ImagePath('2026/11/'.ImageIdMother::DEFAULT_VALUE.'.webp');
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::once())->method('save')->with(self::equalTo($path));
        $storage->expects(self::once())->method('delete')->with(self::equalTo($path));
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::once())->method('save')->willThrowException($failure);

        $this->expectExceptionObject($failure);

        (new ImageUploader(ImageIdMother::DEFAULT_VALUE, ImageMother::ALT, ImageFileMother::path()))($repository, $storage, self::createStub(EventBus::class));
    }

    public function testItSavesNothingWhenTheFileCannotBeKept(): void
    {
        $failure = new \RuntimeException('The disk is full.');
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects(self::once())->method('save')->willThrowException($failure);
        $storage->expects(self::never())->method('delete');
        $repository = $this->createMock(ImageRepository::class);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject($failure);

        (new ImageUploader(ImageIdMother::DEFAULT_VALUE, ImageMother::ALT, ImageFileMother::path()))($repository, $storage, self::createStub(EventBus::class));
    }
}
