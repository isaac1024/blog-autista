<?php

declare(strict_types=1);

namespace Cms\Page\Application;

final class PageNotFound extends \RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No page was found with id "%s".', $id));
    }
}
