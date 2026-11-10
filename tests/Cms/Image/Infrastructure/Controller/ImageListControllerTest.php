<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageSearcher;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the library shows is told by ImageListTest, and how the search finds
 * images by DoctrineImageRepositoryTest; here, that the library is served,
 * and painted, for every shape it takes.
 */
final class ImageListControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheLibraryWithoutAnyImage(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_images');
    }

    /**
     * Every shape the library takes with images behind it is painted: rows
     * of every state, the pages after the first, and a search, which finds
     * nothing here, as full-text search only sees what is committed.
     */
    #[DataProvider('queries')]
    public function testItOpensTheLibraryOfImages(string $query): void
    {
        $client = self::createAuthenticatedClient();
        $repository = self::getContainer()->get(ImageRepository::class);
        $repository->save(ImageMother::published(id: ImageIdMother::random()->value()));
        $repository->save(ImageMother::unpublished(id: ImageIdMother::random()->value()));
        for ($number = 1; $number <= ImageSearcher::PAGE_SIZE; ++$number) {
            $repository->save(ImageMother::create(id: ImageIdMother::random()->value(), alt: 'Ilustración '.$number));
        }

        $client->request('GET', self::URL.$query);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        yield 'the first page' => [''];
        yield 'the second page' => ['?page=2'];
        yield 'a page past the end' => ['?page=9'];
        yield 'a search' => ['?alt=%20faro%20'];
    }

    #[DataProvider('invalidPageNumbers')]
    public function testItAnswersNotFoundForAnInvalidPageNumber(string $page): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL.'?page='.$page);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPageNumbers(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'not a number' => ['abc'];
    }
}
