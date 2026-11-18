<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

/**
 * A form posted without the token of its route, or with a wrong one, which
 * #[IsCsrfTokenValid] refuses, is forbidden, as it was before the attribute:
 * left to the firewall, the refusal would read as signed out and send the
 * editor to the login. It runs before the firewall does.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 2)]
final readonly class RefusedCsrfToken
{
    public function __invoke(ExceptionEvent $event): void
    {
        // Not chained to what it replaces: the firewall looks down the chain too.
        if ($event->getThrowable() instanceof InvalidCsrfTokenException) {
            $event->setThrowable(new AccessDeniedHttpException('The CSRF token is missing or invalid.'));
        }
    }
}
