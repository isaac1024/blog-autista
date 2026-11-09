<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;

/**
 * What a change to a redirect holds while it checks and saves: its address,
 * and the resource it leads to, if any, which must stay on the site.
 */
final readonly class RedirectKeys
{
    public function __construct(
        private Source $source,
        private Target $target,
    ) {}

    /**
     * @return list<string>
     */
    public function __invoke(): array
    {
        $resource = $this->target->resource();

        return [
            'address:'.$this->source->value(),
            ...(null === $resource ? [] : [$resource->key()]),
        ];
    }
}
