<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

/**
 * Reads the primitives of an event body back, failing on the first one
 * that isn't there or has another type.
 */
final readonly class EventBody
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(private array $body) {}

    public function string(string $key): string
    {
        $value = $this->body[$key] ?? null;

        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('The event body has no "%s" string.', $key));
        }

        return $value;
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->body[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('The event body has no "%s" string or null.', $key));
        }

        return $value;
    }

    public function integer(string $key): int
    {
        $value = $this->body[$key] ?? null;

        if (!\is_int($value)) {
            throw new \UnexpectedValueException(\sprintf('The event body has no "%s" integer.', $key));
        }

        return $value;
    }

    public function boolean(string $key): bool
    {
        $value = $this->body[$key] ?? null;

        if (!\is_bool($value)) {
            throw new \UnexpectedValueException(\sprintf('The event body has no "%s" boolean.', $key));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        $strings = [];

        foreach ($this->array($key) as $value) {
            if (!\is_string($value)) {
                throw new \UnexpectedValueException(\sprintf('The event body has no "%s" list of strings.', $key));
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->body[$key] ?? null;

        if (!\is_array($value)) {
            throw new \UnexpectedValueException(\sprintf('The event body has no "%s" array.', $key));
        }

        return $value;
    }
}
