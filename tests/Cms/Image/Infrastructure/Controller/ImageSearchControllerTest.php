<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The images the rich text editor offers to embed. The search runs on a
 * FULLTEXT index, which InnoDB only updates on commit, so the test that
 * searches skips the rollback and tearDown() empties the table.
 */
final class ImageSearchControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images/search';

    #[\Override]
    protected function tearDown(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement('DELETE FROM cms_image');

        parent::tearDown();
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    /**
     * Each image as the editor shows it: its thumbnail where the CMS serves
     * it, its texts, and where it stands for the site.
     */
    #[SkipDatabaseRollback]
    public function testItSearchesTheImagesToEmbedByTheirAlternativeText(): void
    {
        $client = self::createAuthenticatedClient();
        [$published] = $this->saveImages();

        $client->request('GET', self::URL.'?alt=c%C3%ADrculo');

        self::assertResponseIsSuccessful();
        self::assertSame(['images' => [[
            'id' => $published,
            'alt' => 'Círculo naranja sobre fondo azul',
            'caption' => 'Ilustración de archivo.',
            'thumbnail' => '/images/'.$published.'/thumbnail',
            'status' => self::statusLabel('published'),
            'published' => true,
        ]]], self::json($client));
    }

    #[SkipDatabaseRollback]
    public function testItListsEveryImageNewestFirstWithoutText(): void
    {
        $client = self::createAuthenticatedClient();
        [$published, $draft] = $this->saveImages();

        $client->request('GET', self::URL);

        $images = self::json($client)['images'];
        self::assertIsArray($images);
        self::assertSame(
            [$draft, $published],
            array_map(static fn (mixed $image): mixed => \is_array($image) ? $image['id'] : null, $images),
            'newest first',
        );
        self::assertSame(
            [self::statusLabel('draft'), self::statusLabel('published')],
            array_map(static fn (mixed $image): mixed => \is_array($image) ? $image['status'] : null, $images),
        );
    }

    public function testItAnswersNotFoundForAnInvalidPageNumber(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL.'?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * A published image and a draft uploaded after it, without a caption.
     *
     * @return array{string, string}
     */
    private function saveImages(): array
    {
        $published = ImageIdMother::random()->value();
        $draft = ImageIdMother::random()->value();
        $repository = self::getContainer()->get(ImageRepository::class);
        $repository->save(ImageMother::published(id: $published, alt: 'Círculo naranja sobre fondo azul'));
        $repository->save(ImageMother::create(id: $draft, alt: 'Cuadrado blanco sobre fondo verde'));

        return [$published, $draft];
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
