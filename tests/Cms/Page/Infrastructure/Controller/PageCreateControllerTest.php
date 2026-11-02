<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PageCreateControllerTest extends WebTestCase
{
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('POST', 'http://cms.blogautista.localhost/pages', ['title' => 'Posts']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    public function testItAcceptsThePageTitle(): void
    {
        $client = self::createClient();
        $this->login($client);

        $client->request('POST', 'http://cms.blogautista.localhost/pages', ['title' => 'Posts']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/pages', Response::HTTP_SEE_OTHER);
    }

    public function testItRejectsAnEmptyTitle(): void
    {
        $client = self::createClient();
        $this->login($client);

        $client->request('POST', 'http://cms.blogautista.localhost/pages', ['title' => ' ']);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    private function login(KernelBrowser $client): void
    {
        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', [
            '_username' => 'admin',
            '_password' => 'admin',
        ]);
        $client->followRedirect();
    }
}
