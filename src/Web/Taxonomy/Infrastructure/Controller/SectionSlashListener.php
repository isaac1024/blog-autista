<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Hands the address of a section asked for without its final slash to
 * SectionWithoutSlashController, in place of the redirect the router makes
 * of it by itself, to the address with the slash, whatever is there.
 *
 * It runs right after the router has matched the request.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 31)]
final readonly class SectionSlashListener
{
    public function __invoke(RequestEvent $event): void
    {
        $attributes = $event->getRequest()->attributes;

        if ('web_section' !== $attributes->get('_route') || RedirectController::class.'::urlRedirectAction' !== $attributes->get('_controller')) {
            return;
        }

        $attributes->set('_controller', SectionWithoutSlashController::class);
    }
}
