<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\DomainError;

final class InvalidImagePublication extends DomainError
{
    public static function withoutCaption(): self
    {
        return new self('An image cannot be published without a caption.', 'errors.image.publication.without_caption', []);
    }

    public static function nothingToPublish(): self
    {
        return new self('The image is published as it is, so there is nothing to publish.', 'errors.image.publication.nothing_to_publish', []);
    }

    public static function notPublished(): self
    {
        return new self('Only a published image can be unpublished.', 'errors.image.publication.not_published', []);
    }

    public static function redirected(): self
    {
        return new self('An image that redirects lead to stays on the site until they lead elsewhere.', 'errors.image.publication.redirected', []);
    }

    public static function noChangesToDiscard(): self
    {
        return new self('Only a published image with unpublished changes has changes to discard.', 'errors.image.publication.no_changes_to_discard', []);
    }

    public static function publishedImage(): self
    {
        return new self('A published image cannot be deleted; unpublish it first.', 'errors.image.publication.published_image', []);
    }

    public static function inUse(): self
    {
        return new self('An image in use cannot be deleted.', 'errors.image.publication.in_use', []);
    }
}
