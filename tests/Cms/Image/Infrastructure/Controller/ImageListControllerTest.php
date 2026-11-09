<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The image library with real images behind it. The search runs on a FULLTEXT
 * index, which InnoDB only updates on commit, so the test that searches skips
 * the rollback of dama/doctrine-test-bundle and tearDown() empties the table.
 */
final class ImageListControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images';

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

    public function testItInvitesToUploadTheFirstImageWhenThereAreNone(): void
    {
        $client = self::createAuthenticatedClient();

        $crawler = $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_images');
        self::assertSame([], $this->rowAlts($crawler));
        self::assertSame($this->trans('cms.images.empty.title'), $this->emptyStateTitle($crawler));
    }

    /**
     * The only test that commits its data: it saves the images once and
     * requests the library with every kind of query against them.
     */
    #[SkipDatabaseRollback]
    public function testItListsTheImagesForEveryQuery(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveImages();

        $newestFirst = array_reverse(self::alts());
        $circles = ['Círculo lila y círculo rosa sobre fondo morado', 'Círculo naranja sobre fondo azul'];

        foreach ([
            'first page by default, newest first' => ['', \array_slice($newestFirst, 0, 20)],
            'first page asked for explicitly' => ['?page=1', \array_slice($newestFirst, 0, 20)],
            'second page has the rest' => ['?page=2', \array_slice($newestFirst, 20)],
            'blank text does not filter' => ['?alt=%20%20', \array_slice($newestFirst, 0, 20)],
            'search by text, most relevant first' => ['?alt=c%C3%ADrculo', $circles],
            'search ignoring case and accents' => ['?alt=CIRCULO', $circles],
            'search with surrounding spaces' => ['?alt=%20c%C3%ADrculo%20', $circles],
            'search every word, in any order' => ['?alt=azul+c%C3%ADrculo', ['Círculo naranja sobre fondo azul']],
            'search with many results, newest first' => ['?alt=ilustraci%C3%B3n', \array_slice($newestFirst, 0, 18)],
        ] as $case => [$query, $expected]) {
            $crawler = $client->request('GET', self::URL.$query);

            self::assertResponseIsSuccessful($case);
            self::assertSame($expected, $this->rowAlts($crawler), $case);
        }

        foreach ([
            'page past the end' => ['?page=3', 'cms.images.empty.title'],
            'search without results' => ['?alt=tri%C3%A1ngulo', 'cms.images.search.empty.title'],
            'search past its last page' => ['?alt=c%C3%ADrculo&page=2', 'cms.images.search.empty.title'],
        ] as $case => [$query, $emptyState]) {
            $crawler = $client->request('GET', self::URL.$query);

            self::assertResponseIsSuccessful($case);
            self::assertSame([], $this->rowAlts($crawler), $case);
            self::assertSame($this->trans($emptyState), $this->emptyStateTitle($crawler), $case);
        }

        $crawler = $client->request('GET', self::URL.'?alt=%20c%C3%ADrculo%20');
        self::assertSame('círculo', $crawler->filter('#image-search')->attr('value'), 'the search box keeps the trimmed text');

        $crawler = $client->request('GET', self::URL.'?alt=azul');
        self::assertSame(
            '/images/01a1b801-ce58-723d-8234-b76d9105d001/file',
            $crawler->filter('main ul[aria-label] > li img')->attr('src'),
            'the thumbnail is the file of the image, served by the authenticated CMS',
        );
    }

    public function testItAnswersNotFoundForAnInvalidPageNumber(): void
    {
        $client = self::createAuthenticatedClient();

        foreach (['0', '-1', 'abc'] as $page) {
            $client->request('GET', self::URL.'?page='.$page);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND, \sprintf('page=%s', $page));
        }
    }

    /**
     * Keeps the client's kernel between requests so the fixture and the
     * requests share one database connection.
     */
    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    /**
     * Texts oldest first: four images to search by words and eighteen more,
     * so there is a second page of twenty.
     *
     * @return list<string>
     */
    private static function alts(): array
    {
        $alts = [
            'Círculo naranja sobre fondo azul',
            'Cuadrado blanco sobre fondo verde',
            'Círculo lila y círculo rosa sobre fondo morado',
            'Auriculares de cancelación de ruido sobre fondo gris',
        ];

        for ($number = 1; $number <= 18; ++$number) {
            $alts[] = \sprintf('Ilustración de apoyo %d', $number);
        }

        return $alts;
    }

    /**
     * Saves the images with time-ordered ids, oldest first.
     */
    private function saveImages(): void
    {
        $repository = self::getContainer()->get(ImageRepository::class);

        foreach (self::alts() as $index => $alt) {
            $repository->save(ImageMother::create(id: \sprintf('01a1b801-ce58-723d-8234-b76d9105d0%02d', $index + 1), alt: $alt));
        }
    }

    /**
     * @return list<string>
     */
    private function rowAlts(Crawler $crawler): array
    {
        // Every row of the list opens its image through a link named after its alternative text.
        return $crawler->filter('main ul[aria-label] > li a')->each(static fn (Crawler $alt): string => $alt->text());
    }

    private function emptyStateTitle(Crawler $crawler): ?string
    {
        // The empty state's heading is the only one outside the modal.
        $title = $crawler->filter('main h2')->reduce(static fn (Crawler $node): bool => null === $node->closest('dialog'));

        return 0 === $title->count() ? null : $title->text();
    }

    private function trans(string $key): string
    {
        return self::getContainer()->get(TranslatorInterface::class)->trans($key);
    }
}
