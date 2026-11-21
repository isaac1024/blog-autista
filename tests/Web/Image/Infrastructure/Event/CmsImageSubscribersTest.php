<?php

declare(strict_types=1);

namespace Web\Tests\Image\Infrastructure\Event;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Shared\Tests\Infrastructure\Http\ForgottenPages;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Image\Domain\ImageIdMother;

/**
 * The images the CMS publishes and unpublishes, through the events as they
 * arrive, down to the copies on disk, under the directories of the test
 * environment, which tearDown() empties.
 */
final class CmsImageSubscribersTest extends KernelTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        new Filesystem()->copy(CmsImagePublishedMother::file(), self::originals().'/'.CmsImagePublishedMother::PATH);
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ([self::originals(), self::public()] as $directory) {
            if (str_contains($directory, '/var/test/')) {
                new Filesystem()->remove($directory);
            }
        }

        parent::tearDown();
    }

    public function testItPutsOnTheSiteTheImageTheCmsPublishes(): void
    {
        $this->handle('cms.image.published', CmsImagePublishedMother::body());

        self::assertEquals(Image::publish(CmsImagePublishedMother::create()), $this->storedImage());
        self::assertSame(['400w', '800w', '1200w'], $this->copies());
    }

    public function testItReplacesTheTextsWithTheVersionTheCmsPublishesLater(): void
    {
        $caption = Text::stored(Text::paragraph('Otro pie.'));
        $this->handle('cms.image.published', CmsImagePublishedMother::body());

        $this->handle('cms.image.published', CmsImagePublishedMother::body(alt: 'Otro texto', caption: $caption), 2);

        self::assertEquals(
            Image::publish(CmsImagePublishedMother::create(alt: 'Otro texto', caption: $caption)),
            $this->storedImage(),
        );
    }

    public function testItKeepsTheTextsWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle('cms.image.published', CmsImagePublishedMother::body(alt: 'Otro texto'), 2);

        $this->handle('cms.image.published', CmsImagePublishedMother::body());

        self::assertSame('Otro texto', $this->storedImage()?->alt());
    }

    public function testItTakesOffTheSiteTheImageTheCmsUnpublishes(): void
    {
        $this->handle('cms.image.published', CmsImagePublishedMother::body());

        $this->handle('cms.image.unpublished', [], 2);

        self::assertNull($this->storedImage());
        self::assertSame([], $this->copies());
    }

    /**
     * Taken off the site, an image is forgotten by the page cache at every
     * width it was served.
     */
    public function testItHasThePageCacheForgetTheImageTakenOffAtEveryWidth(): void
    {
        $this->handle('cms.image.published', CmsImagePublishedMother::body());
        ForgottenPages::take();

        $this->handle('cms.image.unpublished', [], 2);

        $file = substr(CmsImagePublishedMother::PATH, 0, -\strlen('.webp'));
        self::assertSame(["/media/{$file}-400w.webp", "/media/{$file}-800w.webp", "/media/{$file}-1200w.webp"], ForgottenPages::take());
    }

    public function testItKeepsTheImageWhenItsUnpublicationArrivesAfterALaterPublication(): void
    {
        $this->handle('cms.image.published', CmsImagePublishedMother::body(), 2);

        $this->handle('cms.image.unpublished', []);

        self::assertNotNull($this->storedImage());
        self::assertSame(['400w', '800w', '1200w'], $this->copies());
    }

    public function testItKeepsTheImageAndItsCopiesOffTheSiteWhenItsPublicationIsDeliveredAgain(): void
    {
        $this->handle('cms.image.published', CmsImagePublishedMother::body());
        $this->handle('cms.image.published', CmsImagePublishedMother::body(alt: 'Otro texto'), 2);
        $this->handle('cms.image.unpublished', [], 3);

        // The queue retries the second publication, which it took for failed.
        $this->handle('cms.image.published', CmsImagePublishedMother::body(alt: 'Otro texto'), 2);

        self::assertNull($this->storedImage());
        self::assertSame([], $this->copies());
    }

    public function testItDoesNotRestoreAnImageNorItsCopiesWhenAnOlderPublicationArrivesLate(): void
    {
        $this->handle('cms.image.unpublished', [], revision: 2);

        $this->handle('cms.image.published', CmsImagePublishedMother::body());

        self::assertNull($this->storedImage());
        self::assertSame([], $this->copies());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(string $name, array $body, int $revision = 1): void
    {
        $body['revision'] = $revision;

        // As the worker does after each message, so the projection reads
        // what it keeps from the database, not from Doctrine's memory.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            $name,
            ImageIdMother::DEFAULT_VALUE,
            $body,
        ));
    }

    private function storedImage(): ?Image
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(ImageRepository::class)->find(ImageIdMother::create());
    }

    /**
     * Widths of the copies on disk, narrowest first.
     *
     * @return list<string>
     */
    private function copies(): array
    {
        $copies = glob(\sprintf('%s/2026/11/%s-*.webp', self::public(), ImageIdMother::DEFAULT_VALUE));
        $widths = array_map(static fn (string $copy): string => (string) preg_replace('/^.*-(\d+w)\.webp$/', '$1', $copy), false === $copies ? [] : $copies);
        usort($widths, static fn (string $a, string $b): int => (int) $a <=> (int) $b);

        return $widths;
    }

    private static function originals(): string
    {
        return self::getContainer()->getParameter('web.image.originals_directory');
    }

    private static function public(): string
    {
        return self::getContainer()->getParameter('web.image.public_directory');
    }
}
