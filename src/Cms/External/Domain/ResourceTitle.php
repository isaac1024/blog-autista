<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * What an external resource is called, as it is told to readers: the title
 * of its card, of its frame, and of the menus that lead to it. A single
 * line, with its whitespace collapsed to single spaces and none around it.
 */
final readonly class ResourceTitle
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 200;

    private string $value;

    public function __construct(string $value)
    {
        $this->value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if (mb_strlen($this->value) < self::MIN_LENGTH) {
            throw InvalidResourceTitle::tooShort(self::MIN_LENGTH);
        }

        if (mb_strlen($this->value) > self::MAX_LENGTH) {
            throw InvalidResourceTitle::tooLong(self::MAX_LENGTH);
        }
    }

    /**
     * The title read from the resource, cut to fit, or the host of its
     * address when the resource tells none that fits.
     */
    public static function read(?string $title, ResourceUrl $url): self
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', (string) $title));

        if (mb_strlen($title) > self::MAX_LENGTH) {
            $title = rtrim(mb_substr($title, 0, self::MAX_LENGTH - 1)).'…';
        }

        return new self(mb_strlen($title) >= self::MIN_LENGTH ? $title : $url->address()->host());
    }

    public function value(): string
    {
        return $this->value;
    }
}
