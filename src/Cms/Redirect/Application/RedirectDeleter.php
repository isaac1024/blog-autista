<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Target;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;

/**
 * Deletes a redirect, so its address answers nothing any longer.
 *
 * It holds its address, as every change to a redirect does, and reads the
 * redirect only then, so the site is told of the deletion after the last
 * change, never with the revision of one it missed.
 */
final readonly class RedirectDeleter
{
    public function __construct(private string $id) {}

    public function __invoke(RedirectRepository $redirectRepository, EventBus $eventBus, MutualExclusion $mutualExclusion): void
    {
        $id = new RedirectId($this->id);
        $source = $redirectRepository->sourceOf($id) ?? throw RedirectNotFound::withId($this->id);

        $mutualExclusion->run(new RedirectKeys($source, Target::gone())(), function () use ($id, $redirectRepository, $eventBus): void {
            $redirect = $redirectRepository->find($id) ?? throw RedirectNotFound::withId($this->id);
            $redirect->delete($redirectRepository, $eventBus);
        });
    }
}
