<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the editor offers is told by ImageEditorTest; here, that it is
 * served, and painted, for every shape of image it composes.
 */
final class ImageEditControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images/%s';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, ImageIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    /**
     * Each shape the editor takes, with its own fields and actions, is painted.
     */
    #[DataProvider('imagesOfEveryShape')]
    public function testItOpensTheEditorOfTheImage(Image $image): void
    {
        $client = self::createAuthenticatedClient();
        self::getContainer()->get(ImageRepository::class)->save($image);

        $client->request('GET', \sprintf(self::URL, $image->id()->value()));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_images_edit', ['id' => $image->id()->value()]);
    }

    /**
     * @return iterable<string, array{Image}>
     */
    public static function imagesOfEveryShape(): iterable
    {
        yield 'a draft without a caption' => [ImageMother::create(id: ImageIdMother::random()->value())];
        yield 'a draft with a caption' => [ImageMother::create(id: ImageIdMother::random()->value(), caption: Text::stored(Text::paragraph('Ilustración de archivo.')))];
        yield 'published' => [ImageMother::published(id: ImageIdMother::random()->value())];
        yield 'published with changes' => [ImageMother::published(id: ImageIdMother::random()->value(), alt: 'Un faro', publishedAlt: 'Un faro al anochecer')];
        yield 'unpublished' => [ImageMother::unpublished(id: ImageIdMother::random()->value())];
    }

    public function testItRespondsNotFoundWhenTheImageDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, ImageIdMother::random()->value()));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheIdIsNotAUuid(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', \sprintf(self::URL, 'circulo-naranja'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
