<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;

final class PageListControllerTest extends CmsWebTestCase
{
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/pages');

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItServesTheEmptyPageListToAnAuthenticatedUser(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', 'http://cms.blogautista.localhost/pages');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages');
    }
}
