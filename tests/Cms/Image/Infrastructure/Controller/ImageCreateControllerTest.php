<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\Image;
use Cms\Tests\Image\Domain\ImageFileMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Uploads land in the storage the test environment configures, under var/test,
 * which tearDown() empties: the real images are never touched.
 */
final class ImageCreateControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images';

    #[\Override]
    protected function tearDown(): void
    {
        $directory = self::directory();

        if (str_contains($directory, '/var/test/media')) {
            new Filesystem()->remove($directory);
        }

        parent::tearDown();
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $this->post($client, 'cms_images_create', self::URL, ['alt' => ImageMother::ALT], ['image' => self::upload()]);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertSame([], self::images());
    }

    public function testItKeepsTheImageAndItsFileAndOpensItInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_images_create', self::URL, ['alt' => ImageMother::ALT], ['image' => self::upload()]);

        $images = self::images();
        self::assertCount(1, $images);
        self::assertResponseRedirects('/images/'.$images[0]->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.image.uploaded', 'parameters' => []]], self::successNotices($client));
        self::assertSame([], self::notices($client));
        self::assertCount(1, self::files());
    }

    /**
     * The library tells why, from the key and parameters of the error.
     *
     * @param array<string, string>                                     $fields
     * @param array<string, UploadedFile>                               $files
     * @param array{key: string, parameters: array<string, int|string>} $notice
     */
    #[DataProvider('invalidUploads')]
    public function testItKeepsNothingAndGoesBackToTheLibraryToTellWhy(array $fields, array $files, array $notice): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_images_create', self::URL, $fields, $files);

        self::assertResponseRedirects(self::URL, Response::HTTP_SEE_OTHER);
        self::assertSame([$notice], self::notices($client));
        self::assertSame([], self::images());
        self::assertSame([], self::files());
    }

    /**
     * @return iterable<string, array{array<string, string>, array<string, UploadedFile>, array{key: string, parameters: array<string, int|string>}}>
     */
    public static function invalidUploads(): iterable
    {
        $alt = ['alt' => ImageMother::ALT];
        $missing = ['key' => 'errors.image.file.missing', 'parameters' => []];

        yield 'no file' => [$alt, [], $missing];
        yield 'file that failed to upload' => [$alt, ['image' => self::upload(error: \UPLOAD_ERR_PARTIAL)], $missing];
        yield 'not a WebP' => [$alt, ['image' => self::upload(ImageFileMother::JPEG)], ['key' => 'errors.image.file.not_webp', 'parameters' => []]];
        yield 'wrong size' => [
            $alt,
            ['image' => self::upload(ImageFileMother::LARGER)],
            ['key' => 'errors.image.file.wrong_size', 'parameters' => ['%width%' => 1200, '%height%' => 675]],
        ];
        yield 'no text' => [[], ['image' => self::upload()], ['key' => 'errors.image.alt.empty', 'parameters' => []]];
        yield 'text too long' => [
            ['alt' => str_repeat('a', 126)],
            ['image' => self::upload()],
            ['key' => 'errors.image.alt.too_long', 'parameters' => ['%max%' => 125]],
        ];
    }

    private static function upload(string $name = ImageFileMother::VALID, int $error = \UPLOAD_ERR_OK): UploadedFile
    {
        return new UploadedFile(ImageFileMother::path($name), $name, null, $error, true);
    }

    /**
     * @return list<Image>
     */
    private static function images(): array
    {
        return self::getContainer()->get(EntityManagerInterface::class)->getRepository(Image::class)->findAll();
    }

    /**
     * @return list<string>
     */
    private static function files(): array
    {
        $files = glob(self::directory().'/*/*/*.webp');

        return false === $files ? [] : $files;
    }

    /**
     * The notices flashed for the library, left for it to show.
     *
     * @return array<mixed>
     */
    private static function notices(KernelBrowser $client): array
    {
        $flashes = $client->getRequest()->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashes);

        return $flashes->peek('error');
    }

    private static function directory(): string
    {
        return self::getContainer()->getParameter('cms.image.storage_directory');
    }
}
