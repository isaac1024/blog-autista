<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the pages of the CMS out of the browser's stores, so going back
 * after signing out asks the CMS again, which sends to the login, instead
 * of showing what only the administrator could see; and what its pages ask
 * it, such as the titles of drafts a search finds, with them. Files, such
 * as the images an editor shows, stay as they were: going back shows no
 * file alone.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class UnstoredCmsPages
{
    public function __construct(
        #[Autowire('%env(CMS_HOST)%')]
        private string $cmsHost,
    ) {}

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$event->isMainRequest()
            || $event->getRequest()->getHost() !== $this->cmsHost
            || $response instanceof BinaryFileResponse) {
            return;
        }

        $response->headers->addCacheControlDirective('no-store');
        $response->setPrivate();
    }
}
