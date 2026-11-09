<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;

/**
 * Finds a redirect by its id.
 */
final readonly class RedirectFinder
{
    public function __construct(private string $id) {}

    public function __invoke(RedirectRepository $redirectRepository): RedirectResponse
    {
        $redirect = $redirectRepository->find(new RedirectId($this->id)) ?? throw RedirectNotFound::withId($this->id);

        return RedirectResponse::fromRedirect($redirect);
    }
}
