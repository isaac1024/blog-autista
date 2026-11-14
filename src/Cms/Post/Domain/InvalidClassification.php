<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Shared\Domain\DomainError;

final class InvalidClassification extends DomainError
{
    public static function unknownSection(SectionId $id): self
    {
        return new self(\sprintf('There is no section with id "%s".', $id->value()), 'errors.post.classification.unknown_section', []);
    }

    public static function unknownAuthor(AuthorId $id): self
    {
        return new self(\sprintf('There is no author with id "%s".', $id->value()), 'errors.post.classification.unknown_author', []);
    }

    public static function unknownTag(TagId $id): self
    {
        return new self(\sprintf('There is no tag with id "%s".', $id->value()), 'errors.post.classification.unknown_tag', []);
    }
}
