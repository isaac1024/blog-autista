<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\DomainError;

final class InvalidResourcePublication extends DomainError
{
    public static function withoutImage(): self
    {
        return new self('A video or an audio cannot be published without an image, which its facade shows.', 'errors.external.publication.without_image', []);
    }

    public static function notEmbeddable(): self
    {
        return new self('The resource cannot be published until its provider tells how to embed it; fill it from its address.', 'errors.external.publication.not_embeddable', []);
    }

    public static function withoutAuthor(): self
    {
        return new self('A post cannot be published without who wrote it and the account it was written from.', 'errors.external.publication.without_author', []);
    }

    public static function withoutDate(): self
    {
        return new self('A post cannot be published without the day it was written.', 'errors.external.publication.without_date', []);
    }

    public static function withoutText(): self
    {
        return new self('A post cannot be published without what it says.', 'errors.external.publication.without_text', []);
    }

    public static function withoutAuthors(): self
    {
        return new self('A study cannot be published without who wrote it.', 'errors.external.publication.without_authors', []);
    }

    public static function withoutJournal(): self
    {
        return new self('A study cannot be published without where it was published.', 'errors.external.publication.without_journal', []);
    }

    public static function withoutYear(): self
    {
        return new self('A study cannot be published without the year it was published.', 'errors.external.publication.without_year', []);
    }

    public static function nothingToPublish(): self
    {
        return new self('The resource is published as it is, so there is nothing to publish.', 'errors.external.publication.nothing_to_publish', []);
    }

    public static function notPublished(): self
    {
        return new self('Only a published resource can be unpublished.', 'errors.external.publication.not_published', []);
    }

    public static function noChangesToDiscard(): self
    {
        return new self('Only a published resource with unpublished changes has changes to discard.', 'errors.external.publication.no_changes_to_discard', []);
    }

    public static function publishedResource(): self
    {
        return new self('A published resource cannot be deleted; unpublish it first.', 'errors.external.publication.published_resource', []);
    }

    public static function usedResource(): self
    {
        return new self('A resource that texts or menus use cannot be deleted.', 'errors.external.publication.used_resource', []);
    }

    public static function incompleteDates(): self
    {
        return new self('A resource published once has both a first and a last publication date.', 'errors.external.publication.incomplete_dates', []);
    }
}
