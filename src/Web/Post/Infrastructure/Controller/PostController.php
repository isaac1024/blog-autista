<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Web\Post\Application\PostFinder;
use Web\Post\Application\PostNotFound;
use Web\Post\Domain\PostRepository;

/**
 * A post on the site, under the slug of its section, on the day it was first
 * published, by its slug.
 */
final class PostController extends AbstractController
{
    /**
     * A slug as the CMS lets a slug be.
     */
    private const string SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly PublishedPost $publishedPost,
    ) {}

    #[Route(
        '/{section}/{year}/{month}/{day}/{slug}.html',
        name: 'web_post',
        requirements: ['section' => self::SLUG, 'year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}', 'slug' => self::SLUG],
        methods: ['GET'],
    )]
    public function __invoke(string $section, string $year, string $month, string $day, string $slug, PostRepository $postRepository): Response
    {
        try {
            $post = new PostFinder($section, \sprintf('%s-%s-%s', $year, $month, $day), $slug)($postRepository);
        } catch (PostNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->publishedPost)($post));
    }
}
