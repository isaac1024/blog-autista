<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

/**
 * The tags a post is labelled with, none or any number, each once, in the
 * order the editor gave them.
 */
final readonly class TagIds
{
    /**
     * @var list<TagId>
     */
    private array $ids;

    public function __construct(TagId ...$ids)
    {
        $unique = [];

        foreach ($ids as $id) {
            $unique[$id->value()] ??= $id;
        }

        $this->ids = array_values($unique);
    }

    /**
     * @param list<string> $values
     */
    public static function fromValues(array $values): self
    {
        return new self(...array_map(static fn (string $value): TagId => new TagId($value), $values));
    }

    /**
     * @return list<TagId>
     */
    public function ids(): array
    {
        return $this->ids;
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return array_map(static fn (TagId $id): string => $id->value(), $this->ids);
    }

    public function equals(self $other): bool
    {
        return $this->values() === $other->values();
    }
}
