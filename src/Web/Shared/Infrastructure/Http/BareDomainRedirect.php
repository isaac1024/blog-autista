<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Leads the domain asked for without its www, as people type it, to the
 * same address of the site, for good: the site answers only on its host,
 * and nothing else would answer there but a page not found. The domain is
 * the host of the site without its www, so none is written here.
 *
 * It runs before the router, which knows only the hosts of the site and
 * the CMS.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final readonly class BareDomainRedirect
{
    /**
     * How long browsers and proxies may keep it, in seconds: a day, as the
     * domain doesn't move.
     */
    private const int MAX_AGE = 86400;

    public function __construct(
        #[Autowire(env: 'WEB_HOST')]
        private string $webHost,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !str_starts_with($this->webHost, 'www.') || $request->getHost() !== substr($this->webHost, 4)) {
            return;
        }

        $response = new RedirectResponse(\sprintf('%s://%s%s', $request->getScheme(), $this->webHost, $request->getRequestUri()), Response::HTTP_MOVED_PERMANENTLY);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        $event->setResponse($response);
    }
}
