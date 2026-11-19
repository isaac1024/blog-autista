<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Http\LocalHostsInDev;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A host left in dev by mistake answers nothing of the app, whose dev
 * administrator and secret everyone knows.
 */
final class LocalHostsInDevTest extends TestCase
{
    public function testItAnswersAHostOfAnotherMachineWithABare503(): void
    {
        $event = self::requested('https://www.blogautista.es/login', HttpKernelInterface::MAIN_REQUEST);

        new LocalHostsInDev()($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame([503, 'Service Unavailable', true], [$response->getStatusCode(), $response->getContent(), $response->headers->getCacheControlDirective('no-store')]);
    }

    #[DataProvider('localHosts')]
    public function testItLetsTheHostsOfThisMachineThrough(string $url): void
    {
        $event = self::requested($url, HttpKernelInterface::MAIN_REQUEST);

        new LocalHostsInDev()($event);

        self::assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function localHosts(): iterable
    {
        yield 'the CMS of the Docker stack' => ['http://cms.blogautista.localhost/posts'];
        yield 'localhost' => ['http://localhost:8000/'];
        yield 'the IPv4 loopback' => ['http://127.0.0.1/'];
        yield 'the IPv6 loopback' => ['http://[::1]/'];
    }

    public function testItLeavesWhatTheAppAsksItselfAlone(): void
    {
        $event = self::requested('https://www.blogautista.es/', HttpKernelInterface::SUB_REQUEST);

        new LocalHostsInDev()($event);

        self::assertNull($event->getResponse());
    }

    private static function requested(string $url, int $type): RequestEvent
    {
        return new RequestEvent(self::createStub(HttpKernelInterface::class), Request::create($url), $type);
    }
}
