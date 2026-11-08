<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;

/**
 * Addresses that carry their own permission: a signature over the address and
 * the moment it expires, so whoever holds one reaches it without signing in,
 * until then. Wraps the UriSigner of symfony/http-foundation, so contexts
 * never depend on it directly.
 */
final readonly class SignedUrls
{
    public function __construct(private UriSigner $uriSigner) {}

    /**
     * The address, absolute, signed to be valid until the moment given.
     */
    public function sign(string $url, \DateTimeImmutable $expiresAt): string
    {
        return $this->uriSigner->sign($url, $expiresAt);
    }

    /**
     * Whether the request was made to a signed address, unchanged and not
     * expired.
     */
    public function isValid(Request $request): bool
    {
        return $this->uriSigner->checkRequest($request);
    }
}
