<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class InvalidPagePublication extends DomainError
{
    public static function withoutContent(): self
    {
        return new self('A page cannot be published without content.', 'errors.page.publication.without_content', []);
    }

    public static function withoutSlug(): self
    {
        return new self('A page cannot be published without a slug, except the home page.', 'errors.page.publication.without_slug', []);
    }

    public static function withoutMetaDescription(): self
    {
        return new self('A page cannot be published without a meta description.', 'errors.page.publication.without_meta_description', []);
    }

    public static function nothingToPublish(): self
    {
        return new self('The page is published as it is, so there is nothing to publish.', 'errors.page.publication.nothing_to_publish', []);
    }

    public static function notPublished(): self
    {
        return new self('Only a published page can be unpublished.', 'errors.page.publication.not_published', []);
    }

    public static function redirected(): self
    {
        return new self('A page that redirects lead to stays on the site until they lead elsewhere.', 'errors.page.publication.redirected', []);
    }

    public static function addressOnSite(): self
    {
        return new self('A page on the site keeps its address there.', 'errors.page.publication.address_on_site', []);
    }

    public static function noChangesToDiscard(): self
    {
        return new self('Only a published page with unpublished changes has changes to discard.', 'errors.page.publication.no_changes_to_discard', []);
    }

    public static function uniquePage(PageType $type): self
    {
        return new self(\sprintf('The "%s" page is unique, so it cannot be unpublished.', $type->value), 'errors.page.publication.unique_page', []);
    }

    public static function publishedPage(): self
    {
        return new self('A published page cannot be deleted; unpublish it first.', 'errors.page.publication.published_page', []);
    }

    public static function linkedPage(): self
    {
        return new self('A page linked from other pages, posts, image captions, author bios or the menus cannot be deleted.', 'errors.page.publication.linked_page', []);
    }
}
