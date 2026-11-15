<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Http;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Answers for the sites the tests read, in place of the network: each test
 * records what an address answers, and anything else is not found, so no
 * test ever reaches a real site.
 */
final class RecordedSites
{
    /**
     * What each address answers, its body and the status and headers it
     * comes with, made anew each time it is read.
     *
     * @var array<string, array{string, array<string, mixed>}>
     */
    private static array $answers = [];

    /**
     * @var list<string>
     */
    private static array $asked = [];

    /**
     * @param array<array-key, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        self::$asked[] = $url;

        [$body, $info] = self::$answers[$url] ?? ['', ['http_code' => 404]];

        return new MockResponse($body, $info);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function answer(string $url, string $body, int $status, array $headers): void
    {
        self::$answers[$url] = [$body, ['http_code' => $status, 'response_headers' => $headers]];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function answerJson(string $url, array $data, int $status = 200): void
    {
        self::answer($url, json_encode($data, \JSON_THROW_ON_ERROR), $status, ['content-type' => 'application/json']);
    }

    public static function answerFile(string $url, string $path, string $contentType, int $status = 200): void
    {
        self::answer($url, (string) file_get_contents($path), $status, ['content-type' => $contentType]);
    }

    /**
     * The addresses read since the sites were last forgotten, in order.
     *
     * @return list<string>
     */
    public static function asked(): array
    {
        return self::$asked;
    }

    public static function forget(): void
    {
        self::$answers = [];
        self::$asked = [];
    }
}
