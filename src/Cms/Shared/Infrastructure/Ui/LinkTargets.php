<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What a rich text of the CMS can link to, or show in a widget, as its
 * editor picks it: each kind with where it is searched, the parameter the
 * search takes, the key the answers come under, where those already linked
 * are looked up, and the kind of content it is kept as. Sections and tags
 * are both terms, looked up together.
 */
final readonly class LinkTargets
{
    public function __construct(private UrlGeneratorInterface $urlGenerator) {}

    /**
     * @param ?string $except the page or the post edited, which no text of its own links
     *
     * @return list<array{kind: string, type: string, searchUrl: string, param: string, key: string, lookupUrl: string}>
     */
    public function __invoke(?string $except): array
    {
        $exceptIt = null === $except ? [] : ['except' => $except];
        $terms = $this->urlGenerator->generate('cms_any_terms_lookup');

        return [
            $this->target('page', 'page', $this->urlGenerator->generate('cms_pages_search', $exceptIt), 'title', 'pages', $this->urlGenerator->generate('cms_pages_lookup')),
            $this->target('post', 'post', $this->urlGenerator->generate('cms_posts_search', $exceptIt), 'title', 'posts', $this->urlGenerator->generate('cms_posts_lookup')),
            $this->target('section', 'term', $this->urlGenerator->generate('cms_terms_search', ['taxonomy' => 'sections']), 'name', 'terms', $terms),
            $this->target('tag', 'term', $this->urlGenerator->generate('cms_terms_search', ['taxonomy' => 'tags']), 'name', 'terms', $terms),
            $this->target('author', 'author', $this->urlGenerator->generate('cms_authors_search'), 'name', 'authors', $this->urlGenerator->generate('cms_authors_lookup')),
        ];
    }

    /**
     * @return array{kind: string, type: string, searchUrl: string, param: string, key: string, lookupUrl: string}
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    private function target(string $kind, string $type, string $searchUrl, string $param, string $key, string $lookupUrl): array
    {
        return ['kind' => $kind, 'type' => $type, 'searchUrl' => $searchUrl, 'param' => $param, 'key' => $key, 'lookupUrl' => $lookupUrl];
    }
}
