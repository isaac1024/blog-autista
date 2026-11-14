<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Application;

final class TermNotFound extends \RuntimeException
{
    public static function withId(string $taxonomy, string $id): self
    {
        return new self(\sprintf('No term of the "%s" taxonomy was found with id "%s".', $taxonomy, $id));
    }
}
