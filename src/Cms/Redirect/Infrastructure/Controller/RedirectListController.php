<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\IncomingRedirectsFinder;
use Cms\Redirect\Application\RedirectSearcher;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Domain\DomainError;
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
        $resource = self::resource($target);

        try {
            $redirects = null === $resource
                ? new RedirectSearcher($source, $page)($redirectRepository)
                : new IncomingRedirectsFinder($resource->type(), $resource->id(), $page)($redirectRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->redirectList)($redirects, $source, $resource, $page));
    }

    /**
     * The resource the list is narrowed to, written as "page:<id>", if any.
     */
    private static function resource(string $target): ?Resource
    {
        [$type, $id] = [...explode(':', $target, 2), ''];
        $type = ResourceType::tryFrom($type);

        try {
            return null === $type ? null : new Resource($type, $id);
        } catch (DomainError) {
            return null;
        }
    }
}
