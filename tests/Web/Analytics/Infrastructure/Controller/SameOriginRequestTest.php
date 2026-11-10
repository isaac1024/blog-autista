<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Web\Analytics\Infrastructure\Controller\SameOriginRequest;

/**
 * Whether a request comes from a page of the site itself: as the browser
 * says where it fetches from, or, when it doesn't, by its Origin, held to
 * the same scheme, host and port.
 */
final class SameOriginRequestTest extends TestCase
{
    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('requests')]
    public function testItTellsARequestFromThePagesOfTheSite(array $headers, bool $fromSite): void
    {
        $request = Request::create('http://www.blogautista.localhost/v', 'POST', server: $headers);

        self::assertSame($fromSite, SameOriginRequest::isFromSite($request));
    }

    /**
     * @return iterable<string, array{array<string, string>, bool}>
     */
    public static function requests(): iterable
    {
        yield 'the same origin, as the browser says' => [['HTTP_SEC_FETCH_SITE' => 'same-origin'], true];
        yield 'the same origin, said by the browser over its Origin' => [['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'https://example.org'], true];
        yield 'the Origin of the site, all the browser says' => [['HTTP_ORIGIN' => 'http://www.blogautista.localhost'], true];
        yield 'another site' => [['HTTP_SEC_FETCH_SITE' => 'cross-site'], false];
        yield 'another host of the same site' => [['HTTP_SEC_FETCH_SITE' => 'same-site'], false];
        yield 'another site, whatever its Origin' => [['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'http://www.blogautista.localhost'], false];
        yield 'another origin' => [['HTTP_ORIGIN' => 'https://example.org'], false];
        yield 'the host of the site under another scheme' => [['HTTP_ORIGIN' => 'https://www.blogautista.localhost'], false];
        yield 'the host of the site at another port' => [['HTTP_ORIGIN' => 'http://www.blogautista.localhost:8080'], false];
        yield 'no word of where it comes from' => [[], false];
    }
}
