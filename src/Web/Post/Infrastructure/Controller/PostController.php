<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Controller;

use Shared\Domain\PostPath;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Post\Application\PostFinder;
use Web\Post\Application\PostNotFound;
use Web\Post\Domain\PostRepository;
use Web\Redirect\Application\AddressAnswerFinder;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Domain\TargetAddresses;
use Web\Shared\Infrastructure\Ui\SitePostUrls;

/**
 * A post on the site, the one first published on its day with its slug,
 * looked up by them alone, whatever section the address names:
 *
 * - a post there at an address naming another section, such as the slug
 *   its section had before or one it was moved from, redirects to its
 *   canonical address;
 * - a post there at its canonical address is painted;
 * - no post there, the redirect of /day/slug.html, if any, answers.
 */
final class PostController extends AbstractController
{
    /**
     * A slug as the CMS lets a slug be.
     */
    private const string SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    /**
     * How long browsers and proxies may keep the redirect, in seconds, as
     * the other redirects of the site: the section may change again.
     */
    private const int REDIRECT_MAX_AGE = 3600;

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedPost $publishedPost,
        private readonly SitePostUrls $sitePostUrls,
        private readonly RedirectRepository $redirectRepository,
        private readonly TargetAddresses $targetAddresses,
    ) {}

    #[Route(
        '/{section}/{year}/{month}/{day}/{slug}.html',
        name: 'web_post',
        requirements: ['section' => self::SLUG, 'year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}', 'slug' => self::SLUG],
        methods: ['GET'],
    )]
    public function __invoke(string $year, string $month, string $day, string $slug, Request $request, PostRepository $postRepository): Response
    {
        $date = \sprintf('%s-%s-%s', $year, $month, $day);

        try {
            $post = new PostFinder($date, $slug)($postRepository);
        } catch (PostNotFound $exception) {
            return $this->redirected(PostPath::fromDayAndSlug($date, $slug), $request) ?? throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $canonical = ($this->sitePostUrls)([['sectionId' => $post->sectionId, 'day' => $post->day, 'slug' => $post->slug]], UrlGeneratorInterface::ABSOLUTE_PATH)[0];

        if ($canonical !== $request->getPathInfo()) {
            return $this->movedTo($canonical, $request);
        }

        return $this->pageRenderer->render(($this->publishedPost)($post));
    }

    /**
     * What the redirect of the post's address without its section says:
     * where it leads now, or that it is gone; null when there is none.
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

        return $this->movedTo($answer->location, $request);
    }

    private function movedTo(string $location, Request $request): Response
    {
        $query = $request->getQueryString();
        $response = new RedirectResponse(null === $query ? $location : $location.'?'.$query, Response::HTTP_MOVED_PERMANENTLY);
        $response->setPublic();
        $response->setMaxAge(self::REDIRECT_MAX_AGE);

        return $response;
    }
}
