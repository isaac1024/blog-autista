<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure;

final class HomeControllerTest extends CmsWebTestCase
{
    public function testItOpensOnThePosts(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', 'http://cms.blogautista.localhost/');

        self::assertResponseRedirects('http://cms.blogautista.localhost/posts', 302);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_posts');
    }

    public function testItAsksToSignInFirst(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/');

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testSigningInGoesToThePosts(): void
    {
        $client = self::createLoginClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', ['_username' => 'admin', '_password' => 'admin']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/posts');
    }
}
