<?php

declare(strict_types=1);

namespace Web\Tests\Author\Domain;

use Web\Author\Domain\CmsAuthorUnpublished;

/**
 * Builds the event the CMS sends the site when it unpublishes an author:
 *
 *     CmsAuthorUnpublishedMother::create()
 */
final class CmsAuthorUnpublishedMother
{
    public static function create(string $id = AuthorIdMother::DEFAULT_VALUE, int $revision = 2): CmsAuthorUnpublished
    {
        return new CmsAuthorUnpublished($id, $revision);
    }
}
