<?php

declare(strict_types=1);

namespace Cms\Author\Domain;

use Shared\Domain\DomainError;

final class InvalidAuthorPublication extends DomainError
{
    public static function withoutSlug(): self
    {
        return new self('An author cannot be published without a slug.', 'errors.author.publication.without_slug', []);
    }

    public static function withoutAvatar(): self
    {
        return new self('An author cannot be published without an avatar.', 'errors.author.publication.without_avatar', []);
    }

    public static function withoutBio(): self
    {
        return new self('An author cannot be published without a bio.', 'errors.author.publication.without_bio', []);
    }

    public static function withoutMetaDescription(): self
    {
        return new self('An author cannot be published without a meta description.', 'errors.author.publication.without_meta_description', []);
    }

    public static function nothingToPublish(): self
    {
        return new self('The author is published as it is, so there is nothing to publish.', 'errors.author.publication.nothing_to_publish', []);
    }

    public static function notPublished(): self
    {
        return new self('Only a published author can be unpublished.', 'errors.author.publication.not_published', []);
    }

    public static function noChangesToDiscard(): self
    {
        return new self('Only a published author with unpublished changes has changes to discard.', 'errors.author.publication.no_changes_to_discard', []);
    }

    public static function publishedAuthor(): self
    {
        return new self('A published author cannot be deleted; unpublish it first.', 'errors.author.publication.published_author', []);
    }

    public static function incompleteDates(): self
    {
        return new self('An author published once has both a first and a last publication date.', 'errors.author.publication.incomplete_dates', []);
    }
}
