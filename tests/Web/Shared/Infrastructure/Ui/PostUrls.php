<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure\Ui;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Shared\Infrastructure\Ui\SitePostUrls;
use Web\Taxonomy\Domain\TermRepository;

/**
 * The addresses of posts for a page composed in a test: every section by
 * the slug given, "vida-diaria" unless told otherwise.
 */
trait PostUrls
{
    private static function postUrls(UrlGeneratorInterface $urlGenerator, string $sectionSlug = 'vida-diaria'): SitePostUrls
    {
        $terms = self::createStub(TermRepository::class);
        $terms->method('sectionSlugs')->willReturnCallback(static function (array $ids) use ($sectionSlug): array {
            $slugs = [];

            foreach ($ids as $id) {
                $slugs[\is_string($id) ? $id : ''] = $sectionSlug;
            }

            return $slugs;
        });

        return new SitePostUrls($terms, $urlGenerator);
    }
}
