<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

use Cms\Taxonomy\Domain\Term;

/**
 * Read model of a term handed to the adapters, so they don't touch the
 * aggregate.
 *
 * It carries everything an editor shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class TermResponse
{
    /**
     * @param ?string $slug                  null while it is left without one
     * @param string  $metaDescription       empty when there is none
     * @param string  $robots                one of the Robots values
     * @param string  $status                one of the TermStatus values
     * @param bool    $changed               whether it is published with changes not on the site yet, which can be discarded
     * @param ?string $publishedSlug         the slug it has on the site, null while it isn't there
     * @param bool    $hasSomethingToPublish whether publishing would change the site
     * @param bool    $canBeUnpublished      whether it can be taken off the site
     * @param bool    $canBeDeleted          whether it can be deleted
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $slug,
        public string $metaDescription,
        public string $robots,
        public string $status,
        public bool $changed,
        public ?string $publishedSlug,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public bool $hasSomethingToPublish,
        public bool $canBeUnpublished,
        public bool $canBeDeleted,
    ) {}

    public static function fromTerm(Term $term): self
    {
        return new self(
            $term->id()->value(),
            $term->name()->value(),
            $term->slug()?->value(),
            $term->seo()->description() ?? '',
            $term->seo()->robots()->value,
            $term->status()->value,
            $term->hasUnpublishedChanges(),
            $term->publishedSlug()?->value(),
            $term->timeline()->createdAt(),
            $term->timeline()->updatedAt(),
            $term->hasSomethingToPublish(),
            $term->canBeUnpublished(),
            $term->canBeDeleted(),
        );
    }
}
