<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LogoutControllerTest extends WebTestCase
{
    public function testItLogsOutTheAuthenticatedUser(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', [
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->followRedirect();

        $client->request('GET', 'http://cms.blogautista.localhost/logout');

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');

        $client->request('GET', 'http://cms.blogautista.localhost/');
        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }
}
