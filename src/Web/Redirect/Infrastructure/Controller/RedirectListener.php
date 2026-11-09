<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Web\Redirect\Application\AddressAnswerFinder;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Domain\TargetAddresses;

/**
 * Answers an address of the site with nothing of its own by its redirect,
 * if any: a permanent redirect to where it leads now, keeping the query, or
 * gone, which the site's error page then tells.
 *
 * It only looks once the site has found nothing, whether no route matched or
 * a page wasn't there, so a redirect never hides anything the site has. It
 * runs before the page that tells the site found nothing.
 */
#[AsEventListener(KernelEvents::EXCEPTION, priority: 10)]
final readonly class RedirectListener
{
    /**
     * How long browsers and proxies may keep a redirect, in seconds: a
     * permanent one is otherwise kept for good, and changing or deleting it
     * in the CMS would never reach those who followed it.
     */
    private const int MAX_AGE = 3600;

    public function __construct(
        private RedirectRepository $redirectRepository,
        private TargetAddresses $targetAddresses,
        #[Autowire(env: 'WEB_HOST')]
        private string $webHost,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $exception = $event->getThrowable();

        if (!$exception instanceof NotFoundHttpException || $request->getHost() !== $this->webHost || !\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        $answer = new AddressAnswerFinder($request->getPathInfo())($this->redirectRepository, $this->targetAddresses);

        if (null === $answer) {
            return;
        }

        if (null === $answer->location) {
            $event->setThrowable(new GoneHttpException('What was at this address was taken off the site for good.', $exception));

            return;
        }

        $query = $request->getQueryString();
        $response = new RedirectResponse(null === $query ? $answer->location : $answer->location.'?'.$query, Response::HTTP_MOVED_PERMANENTLY);
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);

        $event->allowCustomResponseCode();
        $event->setResponse($response);
    }
}
