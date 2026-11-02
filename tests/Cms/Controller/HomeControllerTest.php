<?php

declare(strict_types=1);

namespace Cms\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/');

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItServesTheCmsHostToAnAuthenticatedUser(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', [
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->followRedirect();

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
