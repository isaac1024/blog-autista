<?php

declare(strict_types=1);

namespace Web\Post\Domain;

/**
 * A search that finds so many posts it would take longer than a reader
 * should wait, and a process of the site longer than it may be held: it
 * asks for another word rather than for the time.
 */
final class SearchTooBroad extends \RuntimeException
{
    public static function forTerms(SearchTerms $terms, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('The search for "%s" finds too many posts.', implode(' ', $terms->words)), 0, $previous);
    }
}
