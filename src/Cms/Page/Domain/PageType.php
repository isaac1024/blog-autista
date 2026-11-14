<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Slug;

/**
 * Kind of page. The site has each unique page exactly once, created from the
 * console and never deleted, so other parts of the site can rely on it, e.g.
 * the cookie banner linking to the cookie policy. Generic pages are created
 * and deleted from the CMS.
 */
enum PageType: string
{
    case Home = 'home';
    case Contact = 'contact';
    case Search = 'search';
    case LegalNotice = 'legal_notice';
    case PrivacyPolicy = 'privacy_policy';
    case CookiePolicy = 'cookie_policy';
    case Standard = 'standard';

    /**
     * @return list<self>
     */
    public static function uniqueTypes(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => $type->isUnique()));
    }

    public function isUnique(): bool
    {
        return self::Standard !== $this;
    }

    /**
     * The home page is served at the root of the site, so it has no slug.
     */
    public function isHome(): bool
    {
        return self::Home === $this;
    }

    /**
     * The robots directive a new page of this type starts with: listed in
     * search results, except the search page, whose results search engines
     * advise against indexing.
     */
    public function initialRobots(): Robots
    {
        return self::Search === $this ? Robots::NoindexFollow : Robots::IndexFollow;
    }

    /**
     * The slug a page of this type takes from what an editor writes, blank
     * for none: the home page never has one, and any other page may be left
     * without one until it is published.
     */
    public function slugFrom(string $slug): ?Slug
    {
        if ($this->isHome() && '' !== $slug) {
            throw InvalidPageSlug::onHome($slug);
        }

        return '' === $slug ? null : new Slug($slug);
    }
}
