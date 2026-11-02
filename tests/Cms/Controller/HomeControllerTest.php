<?php

declare(strict_types=1);

namespace Cms\Tests\Controller;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;

final class HomeControllerTest extends CmsWebTestCase
{
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/');

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItServesTheCmsHostToAnAuthenticatedUser(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', 'http://cms.blogautista.localhost/');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_home');
    }

    public function testItDoesNotAnswerOnAnUnknownHost(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://admin.blogautista.localhost/');

        self::assertResponseStatusCodeSame(404);
    }
}
