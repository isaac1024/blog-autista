<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Http;

use Cms\Shared\Infrastructure\Ui\CmsErrorPage;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Answers what a request asks the CMS wrong (a 4xx) with a page of the CMS,
 * in the language the browser asks for, rather than with the error page of
 * the site, which leads to the site and calls a link that expired mistyped.
 * The kernel keeps the status of the error; failures are left to the
 * framework, as before.
 *
 * It listens after the framework has logged the error, and after the
 * firewall has sent whoever isn't signed in to the login.
 */
#[AsEventListener(KernelEvents::EXCEPTION, priority: -1)]
final readonly class CmsErrorListener
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        private CmsErrorPage $cmsErrorPage,
        private PageRenderer $pageRenderer,
        private LocaleSwitcher $localeSwitcher,
        #[Autowire(env: 'CMS_HOST')]
        private string $cmsHost,
        #[Autowire(param: 'kernel.enabled_locales')]
        private array $locales,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();

        if ($request->getHost() !== $this->cmsHost || !$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500) {
            return;
        }

        // A route not found is told before the locale of the request is set.
        $event->setResponse($this->localeSwitcher->runWithLocale(
            $request->getPreferredLanguage($this->locales) ?? $request->getDefaultLocale(),
            fn () => $this->pageRenderer->render(($this->cmsErrorPage)(
                $exception->getStatusCode(),
                $request->getPathInfo(),
                $this->referer($request),
            )),
        ));
    }

    /**
     * The page the request came from, only if it is one of the CMS.
     */
    private function referer(Request $request): ?string
    {
        $referer = $request->headers->get('Referer');

        return null !== $referer && parse_url($referer, \PHP_URL_HOST) === $this->cmsHost ? $referer : null;
    }
}
