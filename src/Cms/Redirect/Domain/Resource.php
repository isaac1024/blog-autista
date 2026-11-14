<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

/**
 * A resource of the CMS, by its kind and its id.
 */
final readonly class Resource
{
    public function __construct(
        private ResourceType $type,
        private string $id,
    ) {
        // Rejects what is no id of any resource.
        new ResourceId($id);
    }

    public static function page(string $id): self
    {
        return new self(ResourceType::Page, $id);
    }

    public static function image(string $id): self
    {
        return new self(ResourceType::Image, $id);
    }

    public static function post(string $id): self
    {
        return new self(ResourceType::Post, $id);
    }

    /**
     * A section or a tag.
     */
    public static function term(string $id): self
    {
        return new self(ResourceType::Term, $id);
    }

    public static function author(string $id): self
    {
        return new self(ResourceType::Author, $id);
    }

    public function type(): ResourceType
    {
        return $this->type;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }

    /**
     * Its name for whatever keeps changes over it apart, such as "page:<id>".
     */
    public function key(): string
    {
        return \sprintf('%s:%s', $this->type->value, $this->id);
    }
}
