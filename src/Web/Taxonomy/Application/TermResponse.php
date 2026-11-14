<?php

declare(strict_types=1);

namespace Web\Taxonomy\Application;

use Web\Taxonomy\Domain\Term;

/**
 * Read model of a term on the site handed to the adapters, so they don't
 * touch the aggregate.
 */
final readonly class TermResponse
{
    /**
     * @param ?string $metaDescription null when there is none
     * @param string  $robots          one of the Robots values
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $metaDescription,
        public string $robots,
    ) {}

    public static function fromTerm(Term $term): self
    {
        return new self(
            $term->id()->value(),
            $term->name(),
            $term->slug(),
            $term->metaDescription(),
            $term->robots()->value,
        );
    }
}
