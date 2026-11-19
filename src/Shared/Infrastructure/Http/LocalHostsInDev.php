<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The development environment answers only on this machine: its .env.dev
 * holds an administrator whose password is known and a secret anyone has,
 * which would open the CMS, and sign its previews, on a host left in dev by
 * mistake. Any other host gets a bare 503, before anything else runs, with
 * nothing of the app in it. Only dev has it: prod and test never load it.
 */
#[When(env: 'dev')]
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4096)]
final readonly class LocalHostsInDev
{
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || self::isLocal($event->getRequest()->getHost())) {
            return;
        }

        $event->setResponse(new Response('Service Unavailable', Response::HTTP_SERVICE_UNAVAILABLE, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]));
    }

    /**
     * A host of this machine: localhost and its names, as the Docker stack
     * serves *.blogautista.localhost, and its loopback addresses.
     */
    private static function isLocal(string $host): bool
    {
        return \in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true) || str_ends_with($host, '.localhost');
    }
}
