<?php

declare(strict_types=1);

namespace Web\Taxonomy\Application;

use Web\Taxonomy\Domain\TermRepository;

/**
 * Finds the slug of each section given, as the CMS last published it,
 * whether the section is on the site now or not: the addresses of posts
 * name their sections by it.
 */
final readonly class SectionSlugsFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private array $ids) {}

    /**
     * @return array<string, string> by the id of the section
     */
    public function __invoke(TermRepository $termRepository): array
    {
        return [] === $this->ids ? [] : $termRepository->sectionSlugs(array_values(array_unique($this->ids)));
    }
}
