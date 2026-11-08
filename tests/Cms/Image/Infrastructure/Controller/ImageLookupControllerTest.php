<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The images a rich text embeds, as the editor shows them when it opens.
 */
final class ImageLookupControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images/lookup';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    /**
     * In the order asked; an id of no image, or no id at all, is left out.
     */
    public function testItGivesTheImagesWithTheIdsAsked(): void
    {
        $client = self::createAuthenticatedClient();
        $unpublished = ImageIdMother::random()->value();
        $published = ImageIdMother::random()->value();
        $repository = self::getContainer()->get(ImageRepository::class);
        $repository->save(ImageMother::unpublished(id: $unpublished, alt: 'Círculo naranja sobre fondo azul'));
        // A caption written in runs is told whole.
        $repository->save(ImageMother::published(id: $published, caption: Text::stored(Text::paragraph('Ilustración ', 'de archivo.'))));

        $client->request('GET', self::URL.'?ids='.implode(',', [$unpublished, ImageIdMother::random()->value(), 'foto', '', $published]));

        self::assertResponseIsSuccessful();
        self::assertSame(['images' => [
            [
                'id' => $unpublished,
                'alt' => 'Círculo naranja sobre fondo azul',
                'caption' => 'Ilustración de archivo.',
                'thumbnail' => '/originales/2026/11/'.$unpublished.'.webp',
                'status' => self::statusLabel('unpublished'),
                'published' => false,
            ],
            [
                'id' => $published,
                'alt' => ImageMother::ALT,
                'caption' => 'Ilustración de archivo.',
                'thumbnail' => '/originales/2026/11/'.$published.'.webp',
                'status' => self::statusLabel('published'),
                'published' => true,
            ],
        ]], self::json($client));
    }

    public function testItGivesNoImageWhenNoneIsAsked(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertSame(['images' => []], self::json($client));
    }

    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * @return array<mixed>
     */
    private static function json(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * The badge of a status, in the language of the request.
     */
    private static function statusLabel(string $status): string
    {
        return self::getContainer()->get(TranslatorInterface::class)->trans(\sprintf('cms.images.status.%s.label', $status));
    }
}
