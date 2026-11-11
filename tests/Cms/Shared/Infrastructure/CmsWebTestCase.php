<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure;

use Cms\Authentication\Application\AdministratorFinder;
use Cms\Authentication\Infrastructure\Security\AdministratorUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Base for HTTP tests of the CMS. Authenticated clients get their session
 * straight from the firewall, without going through the login form.
 */
abstract class CmsWebTestCase extends WebTestCase
{
    private const string FIREWALL = 'cms';

    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = self::createClient();
        $administrator = self::getContainer()->get(AdministratorFinder::class)->find('admin');
        $client->loginUser(AdministratorUser::fromAdministrator($administrator), self::FIREWALL);

        return $client;
    }

    /**
     * A client that signs in through the login form from an address of its
     * own, so the attempts throttled per address never add up across tests
     * nor across runs, which share the cache that counts them.
     */
    protected static function createLoginClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->setServerParameter('REMOTE_ADDR', long2ip(random_int(0x0A000001, 0x0AFFFFFE)));

        return $client;
    }

    /**
     * What the last action said it did, left for the page it goes to.
     *
     * @return array<mixed>
     */
    protected static function successNotices(KernelBrowser $client): array
    {
        $flashes = $client->getRequest()->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashes);

        return $flashes->peek('success');
    }

    /**
     * What the last action said went wrong, left for the page it goes to,
     * each by its key and parameters.
     *
     * @return array<mixed>
     */
    protected static function errorNotices(KernelBrowser $client): array
    {
        $flashes = $client->getRequest()->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashes);

        return $flashes->peek('error');
    }

    /**
     * Posts as the browser does when the form for the route supplied the token.
     *
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $files
     */
    protected function post(KernelBrowser $client, string $route, string $url, array $parameters = [], array $files = []): Crawler
    {
        $token = $this->csrfToken($client, $route);

        return $client->request('POST', $url, ['_token' => $token] + $parameters, $files);
    }

    protected function csrfToken(KernelBrowser $client, string $id): string
    {
        $session = $client->getSession() ?? throw new \LogicException('The test client has no session.');
        $request = Request::create('http://cms.blogautista.localhost/');
        $request->setSession($session);
        $requests = self::getContainer()->get(RequestStack::class);
        $requests->push($request);

        try {
            $token = self::getContainer()->get(CsrfTokenManagerInterface::class)->getToken($id)->getValue();
            $session->save();

            return $token;
        } finally {
            $requests->pop();
        }
    }
}
