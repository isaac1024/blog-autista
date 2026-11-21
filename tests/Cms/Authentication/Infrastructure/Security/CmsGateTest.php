<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Security;

use Cms\Authentication\Infrastructure\Security\CmsGate;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * The gate as the server's .env.local sets it, with the user "gate" and the
 * hash of "gate": the tests leave it unset, as development does, so every
 * other test of the CMS goes without it.
 */
final class CmsGateTest extends KernelTestCase
{
    private const string CMS = 'http://cms.blogautista.localhost';

    /**
     * The hash of "gate", made at the cost tests hash with.
     */
    private const string HASH = '$2y$04$IH2iSqRMy0sa1W6RHGi7IeAXp2qj7S8KKCIDDbMRXc6hp2O1o4.mi';

    #[DataProvider('askedWithoutThePassword')]
    public function testItAsksForThePasswordBeforeAnythingOfTheCms(?string $user, ?string $password, string $path): void
    {
        $response = $this->ask(self::gate(), self::CMS.$path, $user, $password);

        self::assertNotNull($response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('Basic realm="CMS", charset="UTF-8"', $response->headers->get('WWW-Authenticate'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
    }

    /**
     * @return iterable<string, array{?string, ?string, string}>
     */
    public static function askedWithoutThePassword(): iterable
    {
        yield 'no password, the login form' => [null, null, '/login'];
        yield 'no password, an address of nothing' => [null, null, '/wp-login.php'];
        yield 'a wrong password' => ['gate', 'otra', '/login'];
        yield 'a wrong user' => ['admin', 'gate', '/login'];
    }

    public function testItLetsTheEditorWhoGaveThePasswordOn(): void
    {
        self::assertNull($this->ask(self::gate(), self::CMS.'/login', 'gate', 'gate'));
    }

    /**
     * Previews, whose signed links an editor hands to others, and robots.txt
     * stay open, as the site does.
     */
    #[DataProvider('openWithoutThePassword')]
    public function testItLeavesOpenWhatOthersMayAskFor(string $address): void
    {
        self::assertNull($this->ask(self::gate(), $address, null, null));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function openWithoutThePassword(): iterable
    {
        yield 'a preview' => [self::CMS.'/preview/posts/0196f3a0-0000-7000-8000-0000000000a1'];
        yield 'an image of a preview' => [self::CMS.'/preview/authors/0196f3a0-0000-7000-8000-0000000000a1/avatar/a.webp'];
        yield 'robots.txt' => [self::CMS.'/robots.txt'];
        yield 'the site' => ['http://www.blogautista.localhost/login'];
    }

    /**
     * Past ten wrong passwords in a quarter of an hour, one address is
     * refused even the right one, which isn't checked; another address
     * still gets in.
     */
    public function testItRefusesAnAddressThatGaveTooManyWrongPasswords(): void
    {
        $gate = self::gate();

        for ($attempt = 1; $attempt <= 10; ++$attempt) {
            self::assertSame(Response::HTTP_UNAUTHORIZED, $this->ask($gate, self::CMS.'/login', 'gate', 'otra-'.$attempt)?->getStatusCode());
        }

        $refused = $this->ask($gate, self::CMS.'/login', 'gate', 'gate');

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $refused?->getStatusCode());
        self::assertSame('no-store, private', $refused->headers->get('Cache-Control'));
        self::assertNull($this->ask($gate, self::CMS.'/login', 'gate', 'gate', '203.0.113.9'));
    }

    /**
     * Production never serves the CMS without its gate: without its
     * settings, the request fails rather than going on.
     */
    public function testProductionRefusesTheCmsWithoutTheSettingsOfItsGate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->ask(self::gate('', '', 'prod'), self::CMS.'/login', null, null);
    }

    /**
     * Elsewhere, without its settings, there is no gate.
     */
    public function testDevelopmentGoesWithoutTheGateWhenItHasNoSettings(): void
    {
        self::assertNull($this->ask(self::gate('', '', 'dev'), self::CMS.'/login', null, null));
    }

    /**
     * It stands before the router, after the redirect to https.
     */
    public function testItRunsOnEveryRequestBeforeTheRouter(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $dispatcher = $container->get(EventDispatcherInterface::class);

        self::assertSame(64, $dispatcher->getListenerPriority(KernelEvents::REQUEST, [$container->get(CmsGate::class), '__invoke']));
    }

    private static function gate(string $user = 'gate', string $hash = self::HASH, string $environment = 'prod'): CmsGate
    {
        self::bootKernel();
        $container = self::getContainer();

        return new CmsGate('cms.blogautista.localhost', $user, $hash, $environment, $container->get('limiter.cms_gate'), $container->get(PasswordHasherFactoryInterface::class));
    }

    /**
     * What the gate answers a request, null when it lets it on.
     */
    private function ask(CmsGate $gate, string $address, ?string $user, ?string $password, string $clientIp = '198.51.100.7'): ?Response
    {
        $server = ['REMOTE_ADDR' => $clientIp];
        if (null !== $user) {
            $server += ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => (string) $password];
        }

        $kernel = self::$kernel ?? throw new \LogicException('The kernel is not booted.');
        $event = new RequestEvent($kernel, Request::create($address, server: $server), HttpKernelInterface::MAIN_REQUEST);
        $gate($event);

        return $event->getResponse();
    }
}
