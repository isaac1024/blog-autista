<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\DomainError;

final class InvalidPostPublication extends DomainError
{
    public static function withoutSlug(): self
    {
        return new self('A post cannot be published without a slug.', 'errors.post.publication.without_slug', []);
    }

    public static function withoutSection(): self
    {
        return new self('A post cannot be published without a section.', 'errors.post.publication.without_section', []);
    }

    public static function withoutAuthor(): self
    {
        return new self('A post cannot be published without an author.', 'errors.post.publication.without_author', []);
    }

    public static function withoutOpeningImage(): self
    {
        return new self('A post cannot be published without an opening image.', 'errors.post.publication.without_opening_image', []);
    }

    public static function withoutContent(): self
    {
        return new self('A post cannot be published without content.', 'errors.post.publication.without_content', []);
    }

    public static function withoutMetaDescription(): self
    {
        return new self('A post cannot be published without a meta description.', 'errors.post.publication.without_meta_description', []);
    }

    public static function sectionOffSite(): self
    {
        return new self('A post cannot be published while its section is off the site.', 'errors.post.publication.section_off_site', []);
    }

    public static function authorOffSite(): self
    {
        return new self('A post cannot be published while its author is off the site.', 'errors.post.publication.author_off_site', []);
    }

    public static function openingImageOffSite(): self
    {
        return new self('A post cannot be published while its opening image is off the site.', 'errors.post.publication.opening_image_off_site', []);
    }

    public static function nothingToPublish(): self
    {
        return new self('The post is published as it is, so there is nothing to publish.', 'errors.post.publication.nothing_to_publish', []);
    }

    public static function notPublished(): self
    {
        return new self('Only a published post can be unpublished.', 'errors.post.publication.not_published', []);
    }

    public static function usedPost(): self
    {
        return new self('A post other contents link to cannot be deleted.', 'errors.post.publication.used_post', []);
    }

    public static function redirected(): self
    {
        return new self('A post that redirects lead to stays on the site until they lead elsewhere.', 'errors.post.publication.redirected', []);
    }

    public static function addressOnSite(): self
    {
        return new self('A post on the site keeps its address there.', 'errors.post.publication.address_on_site', []);
    }

    public static function noChangesToDiscard(): self
    {
        return new self('Only a published post with unpublished changes has changes to discard.', 'errors.post.publication.no_changes_to_discard', []);
    }

    public static function publishedPost(): self
    {
        return new self('A published post cannot be deleted; unpublish it first.', 'errors.post.publication.published_post', []);
    }
}
