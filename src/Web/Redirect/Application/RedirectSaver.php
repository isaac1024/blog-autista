<?php

declare(strict_types=1);

namespace Web\Redirect\Application;

use Web\Redirect\Domain\CmsRedirectSaved;
use Web\Redirect\Domain\Redirect;
use Web\Redirect\Domain\RedirectId;
use Web\Redirect\Domain\RedirectRepository;

/**
 * Puts on the site a redirect the CMS created, or where it leads now.
 */
final readonly class RedirectSaver
{
    public function __construct(private CmsRedirectSaved $event) {}

    public function __invoke(RedirectRepository $redirectRepository): void
    {
        $redirect = $redirectRepository->find(new RedirectId($this->event->aggregateId()));

        if (null === $redirect) {
            $redirect = Redirect::create($this->event);
        } else {
            $redirect->change($this->event);
        }

        $redirect->save($redirectRepository);
    }
}
