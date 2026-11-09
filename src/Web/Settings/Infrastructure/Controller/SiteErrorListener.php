<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Controller;

use Shared\Domain\ImageCopy;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Shared\Infrastructure\Ui\SiteErrorPage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Answers an address of the site with nothing at it with a page of the site,
 * in its chrome, rather than the framework's: one that never had anything
 * is not found, and one whose content was taken off for good is gone.
 *
 * Only the site's host is answered so; the CMS keeps the framework's pages.
 */
#[AsEventListener(KernelEvents::EXCEPTION)]
final readonly class SiteErrorListener
{
    /**
     * How long a gone address may be kept by browsers and proxies, in
     * seconds: short enough that bringing something back there shows soon.
     */
    private const int GONE_MAX_AGE = 3600;

    public function __construct(
        private SiteLayout $siteLayout,
        private SiteErrorPage $siteErrorPage,
        private PageRenderer $pageRenderer,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'WEB_HOST')]
        private string $webHost,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $gone = $exception instanceof GoneHttpException;

        if ((!$gone && !$exception instanceof NotFoundHttpException) || $event->getRequest()->getHost() !== $this->webHost) {
            return;
        }

        // The kernel answers with the status of the exception.
        $response = null !== ImageCopy::fromUrl($event->getRequest()->getPathInfo())
            // An image is asked for by a page, not read: it gets no page.
            ? new Response()
            : $this->page($gone);

        if ($gone) {
            $response->setPublic();
            $response->setMaxAge(self::GONE_MAX_AGE);
        }

        $event->setResponse($response);
    }

    private function page(bool $gone): Response
    {
        $layout = $this->siteLayout->page($this->siteErrorPage->title($gone));
        $homeUrl = $this->urlGenerator->generate('web_home');

        return $this->pageRenderer->render($gone
            ? $this->siteErrorPage->gone($layout, $homeUrl)
            : $this->siteErrorPage->notFound($layout, $homeUrl));
    }
}
