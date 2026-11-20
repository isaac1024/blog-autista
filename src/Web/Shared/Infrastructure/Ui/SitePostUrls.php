<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\PostAddressUrl;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Taxonomy\Application\TermResponse;
use Web\Taxonomy\Application\TermsOnSiteFinder;
use Web\Taxonomy\Domain\TermRepository;

/**
 * The canonical address of posts on the site. A post is the one first
 * published on its day with its slug; the section in front of them only
 * tells where it is filed: the slug its section has on the site, or the one
 * the post kept of it while the section is off it. Another section in the
 * address is answered by a redirect to this one.
 */
final readonly class SitePostUrls
{
    public function __construct(
        private TermRepository $termRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * The address of each post given, in the order given, its sections read
     * at once.
     *
     * @param list<array{sectionId: string, sectionSlug: string, day: string, slug: string}> $posts the section of each and the slug it kept of it, the day it was first published, as Y-m-d, and its slug
     *
     * @return list<string>
     */
    public function __invoke(array $posts, int $referenceType): array
    {
        $sections = new TermsOnSiteFinder(array_values(array_unique(array_column($posts, 'sectionId'))))($this->termRepository);

        return array_map(fn (array $post): string => PostAddressUrl::generate(
            $this->urlGenerator,
            self::section($sections[$post['sectionId']] ?? null, $post['sectionId'], $post['sectionSlug']),
            $post['day'],
            $post['slug'],
            $referenceType,
        ), $posts);
    }

    /**
     * The section a post's address names: the slug of its section on the
     * site, given as found by its id, or the one the post kept of it. A
     * term found in its place, such as the one its address leads to, or a
     * tag, is not its section.
     */
    public static function section(?TermResponse $found, string $sectionId, string $kept): string
    {
        return null !== $found && $found->id === $sectionId && $found->isSection() ? $found->slug : $kept;
    }
}
