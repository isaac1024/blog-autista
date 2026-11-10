<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\IncomingRedirectsFinder;
use Cms\Redirect\Application\RedirectSearcher;
use Cms\Redirect\Domain\RedirectRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The redirects of the site, newest first, searched by their addresses, or
 * only those that lead to a resource.
 */
final class RedirectListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly RedirectList $redirectList,
    ) {}

    #[Route('/redirects', name: 'cms_redirects', methods: ['GET'])]
    public function __invoke(
        RedirectRepository $redirectRepository,
        #[MapQueryParameter]
        string $source = '',
        #[MapQueryParameter]
        int $page = ListPagination::FIRST_PAGE,
        #[MapQueryParameter]
        string $target = '',
    ): Response {
        $resource = NarrowedTarget::fromQuery($target);

        try {
            $redirects = null === $resource
                ? new RedirectSearcher($source, $page)($redirectRepository)
                : new IncomingRedirectsFinder($resource->type(), $resource->id(), $page)($redirectRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->redirectList)($redirects, $source, $resource, $page));
    }
}
