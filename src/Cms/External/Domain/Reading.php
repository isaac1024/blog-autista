<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * What reading an address told of the resource there: what it is and, as
 * far as the resource or its provider tell them, its title, its summary,
 * its language, its details and where its image may be, best first. A reading that
 * couldn't reach the resource tells only what its address does.
 *
 * It carries all a reading tells, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class Reading
{
    /**
     * @param ?string                             $provider the name of the provider that embeds it, if any
     * @param ?string                             $locator  how its provider names it, if it has one
     * @param array<string, null|bool|int|string> $details  as ResourceDetails reads them
     * @param list<string>                        $images   addresses its image may be at, the best first
     * @param bool                                $reached  whether the resource, or its provider, answered
     */
    public function __construct(
        public ResourceKind $kind,
        public ?string $provider,
        public ?string $locator,
        public ?string $title,
        public ?string $summary,
        public ?Language $language,
        public array $details,
        public array $images,
        public bool $reached,
    ) {}
}
