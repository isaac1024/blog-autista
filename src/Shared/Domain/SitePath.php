<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * An address of the site, without its host, written one way only: each
 * segment percent-encoded as browsers send it, and in lowercase. So an
 * address an editor types, with accents or capitals, matches the request a
 * browser makes for it. The query and the fragment are no part of it.
 */
final readonly class SitePath
{
    public const int MAX_LENGTH = 255;

    private function __construct(private string $value) {}

    /**
     * The path of an absolute address of the site, at the given host.
     *
     * @throws InvalidSitePath when it isn't an absolute address of the site, or names its root
     */
    public static function fromUrl(string $url, string $host): self
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (false === $parts || !\in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || !isset($parts['host'])) {
            throw InvalidSitePath::notAbsolute($url);
        }

        if (strtolower($parts['host']) !== strtolower($host)) {
            throw InvalidSitePath::otherHost($url, $host);
        }

        $path = self::normalize($parts['path'] ?? '');

        if ('/' === $path) {
            throw InvalidSitePath::root();
        }

        if (\strlen($path) > self::MAX_LENGTH) {
            throw InvalidSitePath::tooLong(self::MAX_LENGTH);
        }

        return new self($path);
    }

    /**
     * The path a request asks for, or null when it can't be one written here,
     * such as one too long.
     */
    public static function fromRequest(string $pathInfo): ?self
    {
        $path = self::normalize($pathInfo);

        return \strlen($path) > self::MAX_LENGTH ? null : new self($path);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Any text written as the paths are, such as part of one to search for:
     * each segment percent-encoded and in lowercase, its slashes kept.
     */
    public static function write(string $text): string
    {
        return strtolower(implode('/', array_map(
            static fn (string $segment): string => rawurlencode(self::lowercase(rawurldecode($segment))),
            explode('/', $text),
        )));
    }

    /**
     * Any letter in lowercase, accented ones too, so "Guía" and "GUÍA" are
     * written the same; bytes that aren't text are kept as they come.
     */
    private static function lowercase(string $segment): string
    {
        return mb_check_encoding($segment, 'UTF-8') ? mb_strtolower($segment, 'UTF-8') : $segment;
    }

    /**
     * Resolves "." and ".." segments, written or encoded, as a browser does
     * before it asks for an address, so one written with them is the one
     * asked for.
     */
    private static function normalize(string $path): string
    {
        $segments = [];
        $trailing = false;

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            $decoded = rawurldecode($segment);
            $trailing = '.' === $decoded || '..' === $decoded;

            if ('..' === $decoded) {
                array_pop($segments);
            } elseif (!$trailing) {
                $segments[] = $segment;
            }
        }

        // A path ending in a dot segment names a directory, so it keeps its slash.
        if ($trailing) {
            $segments[] = '';
        }

        return '/'.self::write(implode('/', $segments));
    }
}
