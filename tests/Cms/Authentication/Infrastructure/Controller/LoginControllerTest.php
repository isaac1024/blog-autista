<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    public function testItServesTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_login');
    }

    public function testItRejectsInvalidCredentials(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', [
            '_username' => 'admin',
            '_password' => 'wrong-password',
        ]);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_login');
    }
}
