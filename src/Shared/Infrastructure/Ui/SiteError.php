<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

/**
 * Why the site answers an address with an error, as the reader is told: the
 * site has nothing for what was asked, had it and took it off for good, or
 * failed to answer. Each names the copy of its page.
 */
enum SiteError: string
{
    case NotFound = 'not_found';
    case Gone = 'gone';
    case ServerError = 'server_error';

    /**
     * Any error of the server is a failure; of the client errors only gone
     * says something more than that the site has nothing for it, which a
     * reader can't tell from a forbidden or a wrong method anyway.
     */
    public static function fromStatusCode(int $statusCode): self
    {
        return match (true) {
            $statusCode >= 500 => self::ServerError,
            410 === $statusCode => self::Gone,
            default => self::NotFound,
        };
    }
}
