<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The pages a rich text links to, by their ids, for the editor to show their
 * current titles and states. An id of no page is left out.
 */
final class PageLookupController extends AbstractController
{
    public function __construct(private readonly EditorPage $editorPage) {}

    #[Route('/pages/lookup', name: 'cms_pages_lookup', methods: ['GET'])]
    public function __invoke(
        PageRepository $pageRepository,
        #[MapQueryParameter]
        string $ids = '',
    ): JsonResponse {
        $ids = array_values(array_filter(explode(',', $ids), static fn (string $id): bool => '' !== $id));

        return new JsonResponse(['pages' => array_map($this->editorPage, new PagesFinder($ids)($pageRepository))]);
    }
}
