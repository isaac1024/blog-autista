<?php

declare(strict_types=1);

namespace Shared\Domain\External;

/**
 * An absolute address of the web, over HTTP or HTTPS, read into its parts:
 * its host in lowercase, without a "www.", its path and its query.
 */
final readonly class WebAddress
{
    /**
     * @param array<string, string> $query its parameters, the last of each name, in order
     */
    private function __construct(
        private string $scheme,
        private string $host,
        private ?int $port,
        private string $path,
        private array $query,
    ) {}

    /**
     * The address written, or null when it isn't an absolute one over HTTP
     * or HTTPS with a host, and no user or password in it.
     */
    public static function fromString(string $value): ?self
    {
        // No space within, of any script.
        if (1 !== preg_match('/^\S+$/u', trim($value))) {
            return null;
        }

        $parts = parse_url(trim($value));

        if (false === $parts || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = self::asciiHost($parts['host'] ?? '');

        if (!\in_array($scheme, ['http', 'https'], true) || 1 !== preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $host)) {
            return null;
        }

        return new self($scheme, $host, self::port($scheme, $parts['port'] ?? null), $parts['path'] ?? '/', self::query($parts['query'] ?? ''));
    }

    /**
     * Its host, without the "www." it may have.
     */
    public function host(): string
    {
        return str_starts_with($this->host, 'www.') ? substr($this->host, 4) : $this->host;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The segments of its path, without empty ones.
     *
     * @return list<string>
     */
    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path), static fn (string $segment): bool => '' !== $segment));
    }

    public function parameter(string $name): ?string
    {
        return $this->query[$name] ?? null;
    }

    /**
     * The address written in one way, whatever way it came in: its scheme
     * and host in lowercase, no default port, no fragment, and none of the
     * parameters that only track who shared it, such as utm_source.
     */
    public function normalized(): string
    {
        $query = array_filter(
            $this->query,
            static fn (string $name): bool => 1 !== preg_match('/^(utm_[a-z]+|fbclid|gclid|dclid|msclkid|mc_eid|igshid|si|ref_src|ref_url)$/', $name),
            \ARRAY_FILTER_USE_KEY,
        );

        return \sprintf(
            '%s://%s%s%s%s',
            $this->scheme,
            $this->host,
            null === $this->port ? '' : ':'.$this->port,
            $this->path,
            [] === $query ? '' : '?'.http_build_query($query, '', '&', \PHP_QUERY_RFC3986),
        );
    }

    /**
     * A host as one is always kept and compared, however it was written: in
     * lowercase ASCII, an international one in Punycode, as a browser shows
     * it in Unicode, and without the dot that may close a full name. Empty
     * when it can't be the name of a host.
     */
    private static function asciiHost(string $host): string
    {
        $name = str_ends_with($host, '.') ? substr($host, 0, -1) : $host;
        $ascii = '' === $name ? false : idn_to_ascii($name, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46);

        // UTS46 maps it to lowercase too.
        return false === $ascii ? '' : $ascii;
    }

    /**
     * The port, or null for the default one of the scheme.
     */
    private static function port(string $scheme, ?int $port): ?int
    {
        return null === $port || ['http' => 80, 'https' => 443][$scheme] === $port ? null : $port;
    }

    /**
     * The parameters of a query, the last of each name, in order; those
     * that aren't plain text, such as a[]=1, are left out.
     *
     * @return array<string, string>
     */
    private static function query(string $query): array
    {
        parse_str($query, $raw);
        $parameters = [];

        foreach ($raw as $name => $parameter) {
            if (\is_string($parameter)) {
                $parameters[(string) $name] = $parameter;
            }
        }

        return $parameters;
    }
}
