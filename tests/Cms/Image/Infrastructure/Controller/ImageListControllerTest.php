<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;

final class ImageListControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/images';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItShowsTheLibraryToTheAdministrator(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_images');
    }
}
