<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Post\Application\PostFinder;
use Web\Post\Application\PostNotFound;
use Web\Post\Domain\PostRepository;
use Web\Shared\Infrastructure\Ui\SitePostUrls;

/**
 * A post on the site, the one first published on its day with its slug. The
 * section in front of them only tells where it is filed: an address naming
 * another, such as the slug its section had before or one it was moved
 * from, is answered by a permanent redirect to its canonical address.
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
    ) {}

    #[Route(
        '/{section}/{year}/{month}/{day}/{slug}.html',
        name: 'web_post',
        requirements: ['section' => self::SLUG, 'year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}', 'slug' => self::SLUG],
        methods: ['GET'],
    )]
    public function __invoke(string $year, string $month, string $day, string $slug, Request $request, PostRepository $postRepository): Response
    {
        try {
            $post = new PostFinder(\sprintf('%s-%s-%s', $year, $month, $day), $slug)($postRepository);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $canonical = ($this->sitePostUrls)([['sectionId' => $post->sectionId, 'sectionSlug' => $post->sectionSlug, 'day' => $post->day, 'slug' => $post->slug]], UrlGeneratorInterface::ABSOLUTE_PATH)[0];

        if ($canonical !== $request->getPathInfo()) {
            $query = $request->getQueryString();
            $response = new RedirectResponse(null === $query ? $canonical : $canonical.'?'.$query, Response::HTTP_MOVED_PERMANENTLY);
            $response->setPublic();
            $response->setMaxAge(self::REDIRECT_MAX_AGE);

            return $response;
        }

        return $this->pageRenderer->render(($this->publishedPost)($post));
    }
}
