<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Domain\Taxonomy;

/**
 * The segment of the CMS each taxonomy is managed under, such as /sections,
 * which also names the texts of its screens, under cms.{segment}.
 */
enum TaxonomySegment: string
{
    case Sections = 'sections';
    case Tags = 'tags';

    /**
     * What a route takes as the segment of a taxonomy.
     */
    public const string REQUIREMENT = 'sections|tags';

    public function taxonomy(): string
    {
        return match ($this) {
            self::Sections => Taxonomy::Section->value,
            self::Tags => Taxonomy::Tag->value,
        };
    }

    /**
     * The text of a screen of the taxonomy, by its key under cms.{segment}.
     */
    public function key(string $key): string
    {
        return \sprintf('cms.%s.%s', $this->value, $key);
    }
}
