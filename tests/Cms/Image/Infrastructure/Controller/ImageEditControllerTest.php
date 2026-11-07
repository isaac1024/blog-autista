<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Domain\ImageRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Shared\Tests\Domain\RichText\RichTextMother as Text;
use Symfony\Component\HttpFoundation\Response;

final class ImageEditControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images/%s';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', \sprintf(self::URL, ImageIdMother::DEFAULT_VALUE));

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItOpensTheEditorOfAnImageWithoutACaption(): void
    {
        $client = self::createAuthenticatedClient();
        $id = ImageIdMother::random()->value();
        self::getContainer()->get(ImageRepository::class)->save(ImageMother::create(id: $id));

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_images_edit', ['id' => $id]);
    }

    public function testItOpensTheEditorOfAnImageWithACaption(): void
    {
        $client = self::createAuthenticatedClient();
        $id = ImageIdMother::random()->value();
        self::getContainer()->get(ImageRepository::class)->save(
            ImageMother::create(id: $id, caption: Text::stored(Text::paragraph('Ilustración de archivo.'))),
        );

        $client->request('GET', \sprintf(self::URL, $id));

        self::assertResponseIsSuccessful();
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
