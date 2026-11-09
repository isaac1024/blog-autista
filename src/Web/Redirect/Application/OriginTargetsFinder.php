<?php

declare(strict_types=1);

namespace Web\Redirect\Application;

use Web\Redirect\Domain\RedirectRepository;

/**
 * Finds where the redirects of the last addresses of resources off the site
 * lead, by those resources; should two know one, the one last changed does.
 */
final readonly class OriginTargetsFinder
{
    /**
     * @param string       $type "page" or "image"
     * @param list<string> $ids
     */
    public function __construct(
        private string $type,
        private array $ids,
    ) {}

    /**
     * @return array<string, ?array{type: string, id: string}> keyed by the id of each resource off the site; null when its address is gone
     */
    public function __invoke(RedirectRepository $redirectRepository): array
    {
        $targets = [];

        foreach ($redirectRepository->findByOrigins($this->type, $this->ids) as $redirect) {
            $origin = $redirect->originId();

            if (null === $origin || \array_key_exists($origin, $targets)) {
                continue;
            }

            $type = $redirect->targetType();
            $id = $redirect->targetId();
            $targets[$origin] = null === $type || null === $id ? null : ['type' => $type, 'id' => $id];
        }

        return $targets;
    }
}
