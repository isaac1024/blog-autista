<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Shared\Domain\Event\EventBus;

/**
 * Deletes a redirect, so its address answers nothing any longer.
 */
final readonly class RedirectDeleter
{
    public function __construct(private string $id) {}

    public function __invoke(RedirectRepository $redirectRepository, EventBus $eventBus): void
    {
        $redirect = $redirectRepository->find(new RedirectId($this->id)) ?? throw RedirectNotFound::withId($this->id);

        $redirect->delete($redirectRepository, $eventBus);
    }
}
