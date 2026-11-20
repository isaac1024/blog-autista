<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\PostAddressUrl;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Taxonomy\Application\SectionSlugsFinder;
use Web\Taxonomy\Domain\TermRepository;

/**
 * The canonical address of posts on the site. A post is the one first
 * published on its day with its slug; the section in front of them only
 * tells where it is filed, by the slug of the section, on the site or off
 * it. Another section in the address is answered by a redirect to this one.
 */
final readonly class SitePostUrls
{
    public function __construct(
        private TermRepository $termRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * The address of each post given, in the order given, the slugs of their
     * sections read at once.
     *
     * @param list<array{sectionId: string, day: string, slug: string}> $posts the section of each, the day it was first published, as Y-m-d, and its slug
     *
     * @return list<string>
     */
    public function __invoke(array $posts, int $referenceType): array
    {
        $sections = new SectionSlugsFinder(array_column($posts, 'sectionId'))($this->termRepository);

        return array_map(fn (array $post): string => PostAddressUrl::generate(
            $this->urlGenerator,
            $sections[$post['sectionId']] ?? throw new \UnexpectedValueException(\sprintf('No slug of the section "%s" of a post on the site.', $post['sectionId'])),
            $post['day'],
            $post['slug'],
            $referenceType,
        ), $posts);
    }
}
