<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Asks search engines to list nothing of the CMS, nor to follow its links,
 * in every environment: robots.txt keeps them from crawling it, but not from
 * listing an address of it they find linked elsewhere, such as the login.
 * Every answer of its host says so, files included. It runs after the one
 * of Symfony that asks the same, less, of every page while debugging.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -256)]
final readonly class UnindexedCms
{
    public function __construct(
        #[Autowire('%env(CMS_HOST)%')]
        private string $cmsHost,
    ) {}

    public function __invoke(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && $event->getRequest()->getHost() === $this->cmsHost) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex,nofollow');
        }
    }
}
