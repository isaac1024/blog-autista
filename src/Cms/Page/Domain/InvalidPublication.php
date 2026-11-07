<?php

declare(strict_types=1);

namespace Cms\Page\Domain;

use Shared\Domain\DomainError;

final class InvalidPublication extends DomainError
{
    public static function withoutContent(): self
    {
        return new self('A page cannot be published without content.');
    }

    public static function withoutMetaDescription(): self
    {
        return new self('A page cannot be published without a meta description.');
    }

    public static function nothingToPublish(): self
    {
        return new self('The page is published as it is, so there is nothing to publish.');
    }

    public static function notPublished(): self
    {
        return new self('Only a published page can be unpublished.');
    }

    public static function noChangesToDiscard(): self
    {
        return new self('Only a published page with unpublished changes has changes to discard.');
    }

    public static function uniquePage(PageType $type): self
    {
        return new self(\sprintf('The "%s" page is unique, so it cannot be unpublished.', $type->value));
    }

    public static function publishedPage(): self
    {
        return new self('A published page cannot be deleted; unpublish it first.');
    }

    public static function inconsistentState(PageStatus $status): self
    {
        return new self(\sprintf('A %s page cannot be in that state.', $status->value));
    }

    public static function incompleteDates(): self
    {
        return new self('A page published once has both a first and a last publication date.');
    }
}
