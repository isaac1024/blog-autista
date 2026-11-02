<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    public function testItRendersTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[method="post"][action="/login"]');
        self::assertSelectorTextContains('label[for="username"]', 'Username');
        self::assertSelectorTextContains('button[type="submit"]', 'Sign in');
        self::assertSelectorExists('form input[name="_username"]');
        self::assertSelectorExists('form input[name="_password"]');
        self::assertSelectorExists('form input[name="_csrf_token"]');
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
        self::assertSelectorTextContains('[role="alert"]', 'Invalid credentials.');
    }
}
