<?php

declare(strict_types=1);

namespace Web\Author\Application;

use Shared\Domain\RichText\RichText;
use Web\Author\Domain\Author;

/**
 * Read model of an author on the site handed to the adapters, so they don't
 * touch the aggregate.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class AuthorResponse
{
    /**
     * @param string  $avatarName      the name the copies of its avatar share
     * @param ?string $metaDescription null when there is none
     * @param string  $robots          one of the Robots values
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $avatarName,
        public RichText $bio,
        public ?string $metaDescription,
        public string $robots,
    ) {}

    public static function fromAuthor(Author $author): self
    {
        return new self(
            $author->id()->value(),
            $author->name(),
            $author->slug(),
            $author->avatarName(),
            $author->bio(),
            $author->metaDescription(),
            $author->robots()->value,
        );
    }
}
