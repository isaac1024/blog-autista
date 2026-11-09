<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class ImageUpdateControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;

    private const string URL = 'http://cms.blogautista.localhost/images/%s';

    /**
     * The document the editor posts for an empty caption.
     */
    private const string BLANK_DOCUMENT = '{"type":"doc","content":[{"type":"paragraph"}]}';

    /**
     * When the images edited here were uploaded and last changed.
     */
    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the images compared with.
        self::mockTime(ClockMother::now());
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $this->post($client, 'cms_images_update', \sprintf(self::URL, ImageIdMother::DEFAULT_VALUE), self::fields());

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItSavesTheTextsAndGoesBackToTheLibrary(): void
    {
        $client = self::createAuthenticatedClient();
        $image = $this->saveImage();
        // What the editor posts: a Tiptap document, translated into the stored format.
        $caption = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ilustración de archivo.']]],
        ]];

        $this->post($client, 'cms_images_update', \sprintf(self::URL, $image->id()->value()), self::fields([
            'alt' => 'Círculo naranja sobre fondo azul',
            'caption' => json_encode($caption, \JSON_THROW_ON_ERROR),
        ]));

        self::assertResponseRedirects('/images', Response::HTTP_SEE_OTHER);
        self::assertSame([], self::notices($client));
        self::assertEquals(
            ImageMother::create(
                id: $image->id()->value(),
                alt: 'Círculo naranja sobre fondo azul',
                caption: Text::stored(Text::paragraph('Ilustración de archivo.')),
                createdAt: self::BEFORE,
                updatedAt: ClockMother::NOW,
            ),
            $this->storedImage($image),
        );
    }

    public function testItLeavesTheImageWithoutACaptionWhenItIsLeftEmpty(): void
    {
        $client = self::createAuthenticatedClient();
        $image = $this->saveImage(Text::stored(Text::paragraph('Pie anterior.')));

        $this->post($client, 'cms_images_update', \sprintf(self::URL, $image->id()->value()), self::fields());

        self::assertResponseRedirects('/images', Response::HTTP_SEE_OTHER);
        self::assertEquals(
            ImageMother::create(id: $image->id()->value(), createdAt: self::BEFORE, updatedAt: ClockMother::NOW),
            $this->storedImage($image),
        );
    }

    /**
     * The editor tells why, from the key and parameters of the error.
     *
     * @param array<string, string>                                     $fields
     * @param array{key: string, parameters: array<string, int|string>} $notice
     */
    #[DataProvider('invalidFields')]
    public function testItGoesBackToTheEditorToTellWhyAndKeepsTheImage(array $fields, array $notice): void
    {
        $client = self::createAuthenticatedClient();
        $image = $this->saveImage();

        $this->post($client, 'cms_images_update', \sprintf(self::URL, $image->id()->value()), $fields);

        self::assertResponseRedirects(\sprintf(self::URL, $image->id()->value()), Response::HTTP_SEE_OTHER);
        self::assertSame([$notice], self::notices($client));
        self::assertEquals($image, $this->storedImage($image));
    }

    /**
     * @return iterable<string, array{array<string, string>, array{key: string, parameters: array<string, int|string>}}>
     */
    public static function invalidFields(): iterable
    {
        $paragraphs = static fn (string ...$texts): string => json_encode(['type' => 'doc', 'content' => array_map(
            static fn (string $text): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
            $texts,
        )], \JSON_THROW_ON_ERROR);

        yield 'blank alternative text' => [self::fields(['alt' => ' ']), ['key' => 'errors.image.alt.empty', 'parameters' => []]];
        yield 'missing alternative text' => [self::fields(without: ['alt']), ['key' => 'errors.image.alt.empty', 'parameters' => []]];
        yield 'caption too long' => [self::fields(['caption' => $paragraphs(str_repeat('a', 201))]), ['key' => 'errors.image.caption.too_long', 'parameters' => ['%max%' => 200]]];
        yield 'caption of two paragraphs' => [self::fields(['caption' => $paragraphs('Uno.', 'Dos.')]), ['key' => 'errors.image.caption.not_a_single_paragraph', 'parameters' => []]];
        yield 'caption that is not JSON' => [self::fields(['caption' => '<p>Hola</p>']), ['key' => 'errors.rich_text.malformed', 'parameters' => []]];
        yield 'missing caption' => [self::fields(without: ['caption']), ['key' => 'errors.rich_text.malformed', 'parameters' => []]];
    }

    public function testItRespondsNotFoundWhenTheImageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_images_update', \sprintf(self::URL, ImageIdMother::random()->value()), self::fields());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_images_update', \sprintf(self::URL, 'circulo-naranja'), self::fields());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Keeps the client's kernel between requests so the fixture, the request
     * and the assertions share one database connection.
     */
    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * The fields the editor posts: a valid edit with an empty caption, unless
     * a test overrides some or leaves some out.
     *
     * @param array<string, string> $overrides
     * @param list<string>          $without
     *
     * @return array<string, string>
     */
    private static function fields(array $overrides = [], array $without = []): array
    {
        $fields = array_merge(['alt' => ImageMother::ALT, 'caption' => self::BLANK_DOCUMENT], $overrides);

        return array_diff_key($fields, array_flip($without));
    }

    /**
     * @param ?array<array-key, mixed> $caption
     */
    private function saveImage(?array $caption = null): Image
    {
        $image = ImageMother::create(id: ImageIdMother::random()->value(), caption: $caption, createdAt: self::BEFORE);
        self::getContainer()->get(ImageRepository::class)->save($image);

        return $image;
    }

    /**
     * Reads the image back from the database, not from Doctrine's identity map.
     */
    private function storedImage(Image $image): ?Image
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(ImageRepository::class)->find($image->id());
    }

    /**
     * The notices flashed for the page it goes back to, left for it to show.
     *
     * @return array<mixed>
     */
    private static function notices(KernelBrowser $client): array
    {
        $flashes = $client->getRequest()->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashes);

        return $flashes->peek('error');
    }
}
