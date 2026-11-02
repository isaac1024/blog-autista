<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PageListControllerTest extends WebTestCase
{
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/pages');

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItServesTheEmptyPageListToAnAuthenticatedUser(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', [
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->followRedirect();

        $client->request('GET', 'http://cms.blogautista.localhost/pages');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_pages');
    }
}
