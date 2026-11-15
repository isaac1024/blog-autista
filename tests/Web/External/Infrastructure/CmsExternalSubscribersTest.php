<?php

declare(strict_types=1);

namespace Web\Tests\External\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\External\ImageShape;
use Shared\Domain\ExternalImageCopy;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Web\External\Application\ExternalResourcesOnSiteFinder;
use Web\External\Domain\ExternalResourceRepository;

/**
 * The site takes the external resources the CMS publishes, unpublishes and
 * checks, as the worker hands it their events, with the copies of their
 * images, and paints only those available.
 */
final class CmsExternalSubscribersTest extends KernelTestCase
{
    public const string RESOURCE = '01a1e2a0-0000-7000-8000-0000000000b1';

    public const string IMAGE = '01a1e2a0-0000-7000-8000-0000000000a1.webp';

    private const string OTHER_IMAGE = '01a1e2a0-0000-7000-8000-0000000000a2.webp';

    #[\Override]
    protected function setUp(): void
    {
        foreach ([self::IMAGE, self::OTHER_IMAGE] as $image) {
            $file = imagecreatetruecolor(1200, 675);
            self::assertNotFalse($file);
            new Filesystem()->mkdir(self::originals());
            imagewebp($file, self::originals().'/'.$image);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ([self::originals(), self::copies()] as $directory) {
            if (str_contains($directory, '/var/test/')) {
                new Filesystem()->remove($directory);
            }
        }

        parent::tearDown();
    }

    public function testItPaintsTheResourceWithTheCopiesOfItsImage(): void
    {
        $this->dispatch('cms.external.published', self::body());

        self::assertSame([self::RESOURCE => 'Un vídeo'], array_map(static fn ($resource): string => $resource->title, $this->onSite()));

        foreach (ImageShape::Landscape->widths() as $width) {
            $size = getimagesize(self::copies().'/'.new ExternalImageCopy(self::IMAGE, ImageShape::Landscape, $width)->file());
            self::assertNotFalse($size);
            self::assertSame(['image/webp', $width, ImageShape::Landscape->heightAt($width)], [$size['mime'], $size[0], $size[1]]);
        }
    }

    /**
     * A new image names new copies, and those of the one replaced go.
     */
    public function testItReplacesTheVersionAndTheCopiesOfTheImageReplaced(): void
    {
        $this->dispatch('cms.external.published', self::body());

        $this->dispatch('cms.external.published', ['title' => 'Otro título', 'image' => self::OTHER_IMAGE, 'revision' => 2] + self::body());

        self::assertSame('Otro título', $this->onSite()[self::RESOURCE]->title);
        self::assertFileExists(self::copies().'/'.new ExternalImageCopy(self::OTHER_IMAGE, ImageShape::Landscape, 400)->file());
        self::assertFileDoesNotExist(self::copies().'/'.new ExternalImageCopy(self::IMAGE, ImageShape::Landscape, 400)->file());
    }

    public function testItStopsPaintingTheResourceWhileItIsUnavailable(): void
    {
        $this->dispatch('cms.external.published', self::body());

        $this->dispatch('cms.external.availability_changed', ['available' => false, 'revision' => 2]);
        self::assertSame([], $this->onSite());

        // An event of before arrives late, and changes nothing.
        $this->dispatch('cms.external.published', self::body());
        self::assertSame([], $this->onSite());

        $this->dispatch('cms.external.availability_changed', ['available' => true, 'revision' => 3]);
        self::assertSame([self::RESOURCE], array_keys($this->onSite()));
    }

    public function testItChangesNothingOfAResourceOffTheSite(): void
    {
        $this->dispatch('cms.external.availability_changed', ['available' => false, 'revision' => 2]);

        self::assertNull(self::getContainer()->get(ExternalResourceRepository::class)->find(new \Web\External\Domain\ExternalResourceId(self::RESOURCE)));
    }

    public function testItTakesTheResourceOffTheSiteWithTheCopiesOfItsImage(): void
    {
        $this->dispatch('cms.external.published', self::body());

        $this->dispatch('cms.external.unpublished', ['revision' => 2]);
        // The same event twice leaves it gone.
        $this->dispatch('cms.external.unpublished', ['revision' => 3]);

        self::assertSame([], $this->onSite());
        self::assertFileDoesNotExist(self::copies().'/'.new ExternalImageCopy(self::IMAGE, ImageShape::Landscape, 1200)->file());
    }

    public function testItTakesAResourceWithoutImage(): void
    {
        $this->dispatch('cms.external.published', ['kind' => 'social_post', 'provider' => 'bluesky', 'image' => null, 'image_shape' => null, 'details' => ['text' => 'Hola']] + self::body());

        self::assertSame([null, ['text' => 'Hola']], [$this->onSite()[self::RESOURCE]->image, $this->onSite()[self::RESOURCE]->details]);
    }

    /**
     * The body of the event as it travels from the CMS.
     *
     * @return array<string, mixed>
     */
    public static function body(): array
    {
        return [
            'kind' => 'video',
            'provider' => 'youtube',
            'locator' => 'dQw4w9WgXcQ',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'title' => 'Un vídeo',
            'summary' => null,
            'language' => 'es',
            'details' => [],
            'image' => self::IMAGE,
            'image_shape' => 'landscape',
            'available' => true,
            'revision' => 1,
        ];
    }

    /**
     * @return array<string, \Web\External\Application\ExternalResourceOnSite>
     */
    private function onSite(): array
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return new ExternalResourcesOnSiteFinder([self::RESOURCE])(self::getContainer()->get(ExternalResourceRepository::class));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(string $name, array $body): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage($name, self::RESOURCE, $body));
    }

    private static function originals(): string
    {
        return self::getContainer()->getParameter('web.external.originals_directory');
    }

    private static function copies(): string
    {
        return self::getContainer()->getParameter('web.external.public_directory');
    }
}
