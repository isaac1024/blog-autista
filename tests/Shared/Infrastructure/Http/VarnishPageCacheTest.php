<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Http;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Http\VarnishPageCache;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * What the cron of the account asks Hetzner's Varnish, in place of the
 * network: a PURGE of the address of the site, only where it is on, and a
 * warning of the log, never a failure, when Varnish doesn't forget it.
 */
final class VarnishPageCacheTest extends TestCase
{
    public function testItAsksVarnishToForgetTheAddressOfTheSite(): void
    {
        $asked = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$asked): MockResponse {
            $asked[] = $method.' '.$url;

            return new MockResponse('Purged', ['http_code' => 200]);
        });
        $logger = self::logger();

        new VarnishPageCache($client, $logger, true, 'www.blogautista.com')->forget('/sobre-mi.html');

        self::assertSame(['PURGE https://www.blogautista.com/sobre-mi.html'], $asked);
        self::assertSame([], $logger->warnings);
    }

    public function testItAsksNothingWhereItIsOff(): void
    {
        $client = new MockHttpClient(static fn (): never => throw new \LogicException('Varnish was asked.'));

        new VarnishPageCache($client, self::logger(), false, 'www.blogautista.com')->forget('/sobre-mi.html');

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testItWarnsWhenVarnishRefusesToForget(): void
    {
        $logger = self::logger();

        new VarnishPageCache(new MockHttpClient(new MockResponse('Not allowed', ['http_code' => 405])), $logger, true, 'www.blogautista.com')->forget('/sobre-mi.html');

        self::assertSame(['The page cache refused to forget https://www.blogautista.com/sobre-mi.html: status 405'], $logger->warnings);
    }

    public function testItWarnsWhenVarnishCannotBeReached(): void
    {
        $logger = self::logger();
        $client = new MockHttpClient(static fn (): never => throw new TransportException('Connection refused'));

        new VarnishPageCache($client, $logger, true, 'www.blogautista.com')->forget('/sobre-mi.html');

        self::assertSame(['The page cache could not forget https://www.blogautista.com/sobre-mi.html: Connection refused'], $logger->warnings);
    }

    private static function logger(): KeptWarnings
    {
        return new KeptWarnings();
    }
}
