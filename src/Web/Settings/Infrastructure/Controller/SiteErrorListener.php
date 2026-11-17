<?php

declare(strict_types=1);

namespace Web\Settings\Infrastructure\Controller;

use Psr\Log\LoggerInterface;
use Shared\Domain\ImageCopy;
use Shared\Infrastructure\Ui\SiteError;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers every error of the site with a page of the site, in its chrome
 * and its language, rather than the framework's: an address with nothing at
 * it is not found, one whose content was taken off for good is gone, and a
 * failure says so. The kernel keeps the status, and the headers, of the error.
 *
 * Only the site's host is answered so; the CMS keeps the framework's pages.
 * A failure is left to the debug page while debugging, and to the error
 * template, which needs nothing of the database, when its page can't be
 * painted either.
 *
 * It listens after the framework has logged the error, which a response
 * would otherwise keep from being logged.
 */
#[AsEventListener(KernelEvents::EXCEPTION, priority: -1)]
final readonly class SiteErrorListener
{
    /**
     * How long a gone address may be kept by browsers and proxies, in
     * seconds: short enough that bringing something back there shows soon.
     */
    private const int GONE_MAX_AGE = 3600;

    public function __construct(
        private SiteErrorResponse $siteErrorResponse,
        private LoggerInterface $logger,
        #[Autowire(env: 'WEB_HOST')]
        private string $webHost,
        #[Autowire(param: 'kernel.debug')]
        private bool $debug,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $error = SiteError::fromStatusCode($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR);

        if ($event->getRequest()->getHost() !== $this->webHost || (SiteError::ServerError === $error && $this->debug)) {
            return;
        }

        // The kernel answers with the status of the exception.
        $response = null !== ImageCopy::fromUrl($event->getRequest()->getPathInfo())
            // An image is asked for by a page, not read: it gets no page.
            ? new Response()
            : $this->page($error, $event->getRequest()->getPathInfo());

        if (null === $response) {
            return;
        }

        match ($error) {
            SiteError::Gone => $response->setPublic()->setMaxAge(self::GONE_MAX_AGE),
            // A failure may be gone the next moment: no proxy keeps it.
            SiteError::ServerError => $response->headers->addCacheControlDirective('no-store'),
            SiteError::NotFound => null,
        };

        $event->setResponse($response);
    }

    /**
     * The page of the error, or null when painting it failed too, which is
     * logged with what made it fail.
     */
    private function page(SiteError $error, string $path): ?Response
    {
        try {
            return ($this->siteErrorResponse)($error, $path);
        } catch (\Throwable $failure) {
            $this->logger->error('The error page of the site failed to paint: {message}', ['message' => $failure->getMessage(), 'exception' => $failure]);

            return null;
        }
    }
}
