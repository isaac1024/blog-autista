<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Http\RemoteHttp;
use Shared\Infrastructure\Http\RemoteResponse;
use Shared\Infrastructure\Http\RemoteUnreachable;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * How the CMS reads other sites: as a browser that previews links, briefly,
 * never more of an answer than it can use, and telling what came back.
 * The sites answer as they are told here; none is reached.
 */
final class RemoteHttpTest extends TestCase
{
    /**
     * It says what it is and what it wants, in Spanish first, and gives
     * up on a site that takes too long or redirects too often.
     */
    public function testItAsksAsAPreviewOfLinksThatWaitsLittle(): void
    {
        $asked = null;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$asked): MockResponse {
            $asked = [$method, $url, $options['headers'], $options['timeout'], $options['max_duration'], $options['max_redirects'], $options['buffer']];

            return new MockResponse('');
        });

        new RemoteHttp($client)->get('https://www.example.org/noticia.html', 10, 'text/html');

        self::assertIsArray($asked);
        [$method, $url, $headers, $timeout, $maxDuration, $maxRedirects, $buffer] = $asked;
        self::assertSame(['GET', 'https://www.example.org/noticia.html'], [$method, $url]);
        self::assertIsArray($headers);
        self::assertContains('User-Agent: Mozilla/5.0 (compatible; LinkPreview/1.0)', $headers);
        self::assertContains('Accept: text/html', $headers);
        self::assertContains('Accept-Language: es, en;q=0.8', $headers);
        self::assertEquals([8, 15, 5, false], [$timeout, $maxDuration, $maxRedirects, $buffer]);
    }

    /**
     * @param list<string> $chunks the body as it arrives
     */
    #[DataProvider('bodies')]
    public function testItReadsNoMoreOfTheBodyThanItCanUse(array $chunks, int $maxBytes, string $body, bool $truncated): void
    {
        $response = new RemoteHttp(new MockHttpClient(new MockResponse($chunks)))->get('https://www.example.org/', $maxBytes, '*/*');

        self::assertSame([$body, $truncated], [$response->body, $response->truncated]);
    }

    /**
     * @return iterable<string, array{list<string>, int, string, bool}>
     */
    public static function bodies(): iterable
    {
        yield 'shorter than it can use' => [['abc', 'def'], 10, 'abcdef', false];
        yield 'just as long as it can use' => [['abc', 'def'], 6, 'abcdef', false];
        yield 'longer, cut where it stops using it' => [['abc', 'def', 'ghi'], 4, 'abcd', true];
    }

    /**
     * @param array<string, mixed> $info
     */
    #[DataProvider('answers')]
    public function testItTellsWhatTheAnswerIs(array $info, RemoteResponse $expected): void
    {
        self::assertEquals($expected, new RemoteHttp(new MockHttpClient(new MockResponse('cuerpo', $info)))->get('https://www.example.org/noticia.html', 100, '*/*'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, RemoteResponse}>
     */
    public static function answers(): iterable
    {
        $url = 'https://www.example.org/noticia.html';

        yield 'a page, its type written loosely' => [['response_headers' => ['Content-Type' => ' Text/HTML ; charset=UTF-8', 'Content-Length' => '6']], new RemoteResponse(200, $url, false, 'text/html', 6, 'cuerpo', false)];
        yield 'an answer that tells neither its type nor its length' => [[], new RemoteResponse(200, $url, false, null, null, 'cuerpo', false)];
        yield 'a length that is no number' => [['response_headers' => ['Content-Length' => 'seis']], new RemoteResponse(200, $url, false, null, null, 'cuerpo', false)];
        yield 'an error, read as any other answer' => [['http_code' => 404], new RemoteResponse(404, $url, false, null, null, 'cuerpo', false)];
        yield 'an answer a redirect led to' => [['url' => 'https://www.example.org/2026/noticia.html', 'redirect_count' => 2], new RemoteResponse(200, 'https://www.example.org/2026/noticia.html', true, null, null, 'cuerpo', false)];
        yield 'an answer no redirect led to' => [['redirect_count' => 0], new RemoteResponse(200, $url, false, null, null, 'cuerpo', false)];
    }

    public function testItTellsWhenNoAnswerComes(): void
    {
        $this->expectException(RemoteUnreachable::class);
        $this->expectExceptionMessage('"https://www.example.org/" could not be read');

        new RemoteHttp(new MockHttpClient(new MockResponse('', ['error' => 'Could not resolve host'])))->get('https://www.example.org/', 100, '*/*');
    }
}
