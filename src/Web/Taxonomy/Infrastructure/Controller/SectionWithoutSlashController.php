<?php

declare(strict_types=1);

namespace Web\Taxonomy\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Web\Redirect\Application\AddressAnswerFinder;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Domain\TargetAddresses;
use Web\Shared\Infrastructure\Http\SiteCacheLifetimes;
use Web\Taxonomy\Application\TermFinder;
use Web\Taxonomy\Application\TermNotFound;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Answers the address of a section asked for without its final slash, at
 * once, so whatever is asked for there reaches PHP once and the cache keeps
 * it, as a sweep of bots asks for /admin or /backup:
 *
 * - a section on the site leads to its address;
 * - none, the redirect of the address with the slash, if any, answers;
 * - nothing there is not found.
 *
 * The router would lead any such address to the one with the slash,
 * uncached, even when nothing is there; SectionSlashListener hands it here
 * instead.
 */
final class SectionWithoutSlashController extends AbstractController
{
    /**
     * How long browsers and proxies may keep a redirect the CMS keeps, in
     * seconds, as the other redirects of the site.
     */
    private const int REDIRECT_MAX_AGE = 3600;

    public function __construct(
        private readonly RedirectRepository $redirectRepository,
        private readonly TargetAddresses $targetAddresses,
    ) {}

    public function __invoke(string $slug, Request $request, TermRepository $termRepository): Response
    {
        try {
            new TermFinder(Taxonomy::Section->value, $slug)($termRepository);
        } catch (TermNotFound $exception) {
            return $this->redirected(\sprintf('/%s/', $slug), $request) ?? throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        // The section lists posts as they come, so the cache keeps the way
        // to it as long as the section itself.
        $response = $this->movedTo($this->generateUrl('web_section', ['slug' => $slug]), $request);
        $response->setMaxAge(0);
        $response->setSharedMaxAge(SiteCacheLifetimes::LIFETIME);

        return $response;
    }

    /**
     * What the redirect of the address with the slash says: where it leads
     * now, or that it is gone; null when there is none.
     */
    private function redirected(string $path, Request $request): ?Response
    {
        $answer = new AddressAnswerFinder($path)($this->redirectRepository, $this->targetAddresses);

        if (null === $answer) {
            return null;
        }

        if (null === $answer->location) {
            throw new GoneHttpException('What was at this address was taken off the site for good.');
        }

        $response = $this->movedTo($answer->location, $request);
        $response->setMaxAge(self::REDIRECT_MAX_AGE);

        return $response;
    }

    private function movedTo(string $location, Request $request): Response
    {
        $query = $request->getQueryString();
        $response = new RedirectResponse(null === $query ? $location : $location.'?'.$query, Response::HTTP_MOVED_PERMANENTLY);
        $response->setPublic();

        return $response;
    }
}
