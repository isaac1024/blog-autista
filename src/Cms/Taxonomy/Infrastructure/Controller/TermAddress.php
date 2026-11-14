<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where the site serves the terms of a taxonomy, by their slugs: sections at
 * the first level of the site, tags under /tag/.
 */
final readonly class TermAddress
{
    /**
     * Stands for the slug in an address, as the search preview takes it.
     */
    private const string SLUG = '%slug%';

    /**
     * Made of slug characters, so it survives the encoding of the generator,
     * then takes the place of the slug.
     */
    private const string PLACEHOLDER = 'slug-placeholder';

    public function __construct(private UrlGeneratorInterface $urlGenerator) {}

    /**
     * The address of the term on the site, absolute or as a path.
     */
    public function address(TaxonomySegment $taxonomy, string $slug, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->urlGenerator->generate(self::route($taxonomy), ['slug' => $slug], $referenceType);
    }

    /**
     * The absolute address of any term of the taxonomy, with %slug% where its
     * slug goes.
     */
    public function pattern(TaxonomySegment $taxonomy): string
    {
        return str_replace(self::PLACEHOLDER, self::SLUG, $this->address($taxonomy, self::PLACEHOLDER, UrlGeneratorInterface::ABSOLUTE_URL));
    }

    /**
     * What the path of a term has before and after its slug, such as "/tag/"
     * and ".html", for the field the slug is written in.
     *
     * @return array{prefix: string, suffix: string}
     */
    public function affixes(TaxonomySegment $taxonomy): array
    {
        [$prefix, $suffix] = explode(self::PLACEHOLDER, $this->address($taxonomy, self::PLACEHOLDER), 2);

        return ['prefix' => $prefix, 'suffix' => $suffix];
    }

    private static function route(TaxonomySegment $taxonomy): string
    {
        return match ($taxonomy) {
            TaxonomySegment::Sections => 'web_section',
            TaxonomySegment::Tags => 'web_tag',
        };
    }
}
