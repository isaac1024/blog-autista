<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Ui;

use Cms\Shared\Infrastructure\Ui\LinkTargets;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;

/**
 * What a rich text of the CMS links to, as its editor picks it: pages,
 * posts, sections, tags and authors, in that order, each searched and
 * looked up where the CMS keeps it, sections and tags as terms looked up
 * together. Routes are generated as their names.
 */
final class LinkTargetsTest extends TestCase
{
    public function testItOffersEveryKindOfContentWhereTheCmsKeepsIt(): void
    {
        self::assertSame([
            ['kind' => 'page', 'type' => 'page', 'searchUrl' => '/cms_pages_search', 'param' => 'title', 'key' => 'pages', 'lookupUrl' => '/cms_pages_lookup'],
            ['kind' => 'post', 'type' => 'post', 'searchUrl' => '/cms_posts_search', 'param' => 'title', 'key' => 'posts', 'lookupUrl' => '/cms_posts_lookup'],
            ['kind' => 'section', 'type' => 'term', 'searchUrl' => '/cms_terms_search?taxonomy=sections', 'param' => 'name', 'key' => 'terms', 'lookupUrl' => '/cms_any_terms_lookup'],
            ['kind' => 'tag', 'type' => 'term', 'searchUrl' => '/cms_terms_search?taxonomy=tags', 'param' => 'name', 'key' => 'terms', 'lookupUrl' => '/cms_any_terms_lookup'],
            ['kind' => 'author', 'type' => 'author', 'searchUrl' => '/cms_authors_search', 'param' => 'name', 'key' => 'authors', 'lookupUrl' => '/cms_authors_lookup'],
        ], new LinkTargets(new RouteNameUrlGenerator())(null));
    }

    /**
     * The page or the post edited is left out of the searches of its kind.
     */
    public function testItLeavesOutOfThePagesAndPostsTheOneEdited(): void
    {
        $targets = new LinkTargets(new RouteNameUrlGenerator())('01a1b801-ce58-723d-8234-b76d9105d001');

        self::assertSame(
            ['/cms_pages_search?except=01a1b801-ce58-723d-8234-b76d9105d001', '/cms_posts_search?except=01a1b801-ce58-723d-8234-b76d9105d001', '/cms_terms_search?taxonomy=sections', '/cms_terms_search?taxonomy=tags', '/cms_authors_search'],
            array_column($targets, 'searchUrl'),
        );
    }
}
