<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

/**
 * What another site answered to an address.
 *
 * It carries all an answer tells, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class RemoteResponse
{
    /**
     * @param string  $url           the address that answered, once redirects were followed
     * @param bool    $redirected    whether a redirect led there
     * @param ?string $contentType   its media type, in lowercase, without parameters
     * @param ?int    $contentLength how long the whole body is, as the answer tells it
     * @param bool    $truncated     whether the body was longer than what was read
     */
    public function __construct(
        public int $status,
        public string $url,
        public bool $redirected,
        public ?string $contentType,
        public ?int $contentLength,
        public string $body,
        public bool $truncated,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * The body read as a JSON object, or null when it isn't one.
     *
     * @return ?array<array-key, mixed>
     */
    public function json(): ?array
    {
        if ($this->truncated) {
            return null;
        }

        try {
            $data = json_decode($this->body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }
}
