<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsFinder;
use Cms\Taxonomy\Domain\TermRepository;

/**
 * The slug the address of a post names its section by, as the site does:
 * the one the section has on the site, or the last one it had there while
 * it is off it, published or not. A section never on the site, or whose
 * last address another took, by the slug it is saved with.
 */
final readonly class CmsSectionSlugs
{
    public function __construct(private TermRepository $termRepository) {}

    /**
     * The slug of each section given, by id, read at once; an id of no
     * section is left out.
     *
     * @param list<string> $ids
     *
     * @return array<string, string>
     */
    public function __invoke(array $ids): array
    {
        $slugs = [];

        foreach (new TermsFinder(array_values(array_unique($ids)))($this->termRepository) as $term) {
            $slug = $term->publishedSlug ?? $term->lastSiteSlug ?? $term->slug;

            if (TermResponse::SECTION === $term->taxonomy && null !== $slug) {
                $slugs[$term->id] = $slug;
            }
        }

        return $slugs;
    }
}
