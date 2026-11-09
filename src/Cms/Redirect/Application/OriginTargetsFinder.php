<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;

/**
 * Finds where the redirects of the last addresses of resources off the site
 * lead, by those resources, as the CMS has them.
 */
final readonly class OriginTargetsFinder
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private ResourceType $type,
        private array $ids,
    ) {}

    /**
     * @return array<string, ?array{type: string, id: string}> keyed by the id of each resource off the site; null when its address is gone
     */
    public function __invoke(RedirectRepository $redirectRepository): array
    {
        $targets = [];

        foreach ($redirectRepository->findByOrigins($this->type, $this->ids) as $redirect) {
            $origin = $redirect->origin()->resource();

            if (null === $origin) {
                continue;
            }

            $target = $redirect->target()->resource();
            $targets[$origin->id()] = null === $target ? null : ['type' => $target->type()->value, 'id' => $target->id()];
        }

        return $targets;
    }
}
