<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Controller;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class LogoutControllerTest extends CmsWebTestCase
{
    public function testItLogsOutTheAuthenticatedUser(): void
    {
        $client = self::createLoginClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', [
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->followRedirect();

        $client->request('GET', 'http://cms.blogautista.localhost/logout');
        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);

        $client->request('POST', 'http://cms.blogautista.localhost/logout');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $token = $this->csrfToken($client, 'logout');
        $client->request('POST', 'http://cms.blogautista.localhost/logout', ['_csrf_token' => $token]);
        self::assertResponseRedirects('http://cms.blogautista.localhost/login');

        $client->request('GET', 'http://cms.blogautista.localhost/pages');
        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }
}
