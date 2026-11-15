<?php

declare(strict_types=1);

namespace Web\Tests\Post\Domain;

use Web\Post\Domain\CmsPostUnpublished;

/**
 * Builds the event the CMS sends the site when it unpublishes a post:
 *
 *     CmsPostUnpublishedMother::create()
 */
final class CmsPostUnpublishedMother
{
    public static function create(string $id = PostIdMother::DEFAULT_VALUE, int $revision = 2): CmsPostUnpublished
    {
        return new CmsPostUnpublished($id, $revision);
    }
}
