<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Domain;

use Shared\Domain\DomainError;

final class InvalidTermPublication extends DomainError
{
    public static function withoutSlug(): self
    {
        return new self('A term cannot be published without a slug.', 'errors.term.publication.without_slug', []);
    }

    public static function withoutMetaDescription(): self
    {
        return new self('A term cannot be published without a meta description.', 'errors.term.publication.without_meta_description', []);
    }

    public static function nothingToPublish(): self
    {
        return new self('The term is published as it is, so there is nothing to publish.', 'errors.term.publication.nothing_to_publish', []);
    }

    public static function notPublished(): self
    {
        return new self('Only a published term can be unpublished.', 'errors.term.publication.not_published', []);
    }

    public static function redirected(): self
    {
        return new self('A term that redirects lead to stays on the site until they lead elsewhere.', 'errors.term.publication.redirected', []);
    }

    public static function addressOnSite(): self
    {
        return new self('A term on the site keeps its address there.', 'errors.term.publication.address_on_site', []);
    }

    public static function noChangesToDiscard(): self
    {
        return new self('Only a published term with unpublished changes has changes to discard.', 'errors.term.publication.no_changes_to_discard', []);
    }

    public static function publishedTerm(): self
    {
        return new self('A published term cannot be deleted; unpublish it first.', 'errors.term.publication.published_term', []);
    }

    public static function usedTerm(): self
    {
        return new self('A term posts are filed under cannot be deleted.', 'errors.term.publication.used_term', []);
    }

    public static function incompleteDates(): self
    {
        return new self('A term published once has both a first and a last publication date.', 'errors.term.publication.incomplete_dates', []);
    }
}
