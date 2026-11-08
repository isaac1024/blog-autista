<?php

declare(strict_types=1);

namespace Shared\Domain;

/**
 * Base of every error raised when a domain rule is broken, so adapters can
 * handle them apart from unexpected failures.
 *
 * Besides its message, for logs and developers, each error names what went
 * wrong with a key and its parameters, so adapters can tell it to people in
 * their own words.
 *
 * Every rule broken has an error of its own, so the errors grow in number
 * with the rules of the domain rather than with how it is designed.
 *
 * @SuppressWarnings("PHPMD.NumberOfChildren")
 */
abstract class DomainError extends \DomainException
{
    /**
     * @param string                    $key        identifies what went wrong, such as "errors.image.alt.too_long"
     * @param array<string, int|string> $parameters the values the key needs to be told, such as "%max%"
     */
    final public function __construct(
        string $message,
        private readonly string $key,
        private readonly array $parameters,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    final public function key(): string
    {
        return $this->key;
    }

    /**
     * @return array<string, int|string>
     */
    final public function parameters(): array
    {
        return $this->parameters;
    }
}
