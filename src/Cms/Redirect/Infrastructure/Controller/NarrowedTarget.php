<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Shared\Domain\DomainError;

/**
 * The resource a list of redirects is narrowed to, as its address writes
 * it: "page:<id>" or "image:<id>", as Resource::key() gives it. Anything
 * else narrows the list to nothing.
 */
final class NarrowedTarget
{
    public static function fromQuery(string $target): ?Resource
    {
        [$type, $id] = [...explode(':', $target, 2), ''];
        $type = ResourceType::tryFrom($type);

        try {
            return null === $type ? null : new Resource($type, $id);
        } catch (DomainError) {
            return null;
        }
    }
}
