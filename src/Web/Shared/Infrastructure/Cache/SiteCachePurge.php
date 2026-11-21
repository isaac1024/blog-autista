<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Cache;

use Shared\Domain\ImageCopy;
use Shared\Domain\PageCache;
use Web\Redirect\Domain\TargetAddresses;

/**
 * Has the page cache forget a resource of the site once a change to it is
 * applied: where it was served before the change, read before, as once taken
 * off the site it has no address, and where it is served after it, should
 * that differ. An image is forgotten at every width it is served.
 *
 * For what is taken off the site, or corrected, which is otherwise shown
 * until it expires; the lists that show it expire by themselves.
 */
final readonly class SiteCachePurge
{
    public function __construct(
        private TargetAddresses $targetAddresses,
        private PageCache $pageCache,
    ) {}

    /**
     * @param string           $type   "page", "post", "term", "author" or "image"
     * @param \Closure(): void $change what changes it on the site, committed when it returns
     */
    public function around(string $type, string $id, \Closure $change): void
    {
        $before = $this->addresses($type, $id);

        $change();

        foreach (array_unique([...$before, ...$this->addresses($type, $id)]) as $address) {
            $this->pageCache->forget($address);
        }
    }

    /**
     * Where the resource is served, at every width for an image; none that
     * can't be told, as a post whose section slug hasn't reached the site:
     * forgetting is best done, and never keeps a change off the site.
     *
     * @return list<string>
     */
    private function addresses(string $type, string $id): array
    {
        $addresses = [];

        foreach ('image' === $type ? array_keys(ImageCopy::SIZES) : [null] as $width) {
            try {
                $addresses[] = $this->targetAddresses->addressOf($type, $id, $width);
            } catch (\UnexpectedValueException) {
                continue;
            }
        }

        return array_values(array_filter($addresses, static fn (?string $address): bool => null !== $address));
    }
}
