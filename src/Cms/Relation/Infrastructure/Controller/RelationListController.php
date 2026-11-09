<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Cms\Relation\Application\UsersFinder;
use Cms\Relation\Application\UsersResponse;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where a page or an image is used: every resource of the CMS that relates
 * to it, each leading to where it is edited, apart from the editor itself.
 *
 * It finds the page or the image it lists the users of, so its coupling grows
 * with the kinds of resource and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class RelationListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly UserRows $userRows,
        private readonly ListPagination $listPagination,
    ) {}

    /**
     * The type is the section of the CMS the resource is edited under.
     */
    #[Route('/{type}/{id}/relations', name: 'cms_relations', requirements: ['type' => 'pages|images', 'id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(
        string $type,
        string $id,
        RelationRepository $relationRepository,
        PageRepository $pageRepository,
        ImageRepository $imageRepository,
        #[MapQueryParameter]
        int $page = 1,
    ): Response {
        $resourceType = 'pages' === $type ? ResourceType::Page : ResourceType::Image;

        try {
            $name = ResourceType::Page === $resourceType
                ? new PageFinder($id)($pageRepository)->title
                : new ImageFinder($id)($imageRepository)->alt;
        } catch (PageNotFound|ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        try {
            $users = new UsersFinder($resourceType, $id, $page)($relationRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($this->page($resourceType, $type, $id, $name, $users, $page));
    }

    /**
     * Composes the whole page from Twig components, so it takes what each of
     * them shows.
     *
     * @param string $section where the resource is edited in the CMS, such as "pages"
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    private function page(ResourceType $type, string $section, string $id, string $name, UsersResponse $users, int $page): Page
    {
        $texts = \sprintf('cms.relations.%s', $type->value);
        $rows = ($this->userRows)($users->users);

        $layout = CmsLayout::page(\sprintf(
            '%s · %s · CMS',
            $name,
            $this->translator->trans('cms.relations.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.relations.title'),
                'subtitle' => $this->translator->trans($texts.'.subtitle', ['%name%' => $name]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl(\sprintf('cms_%s_edit', $section), ['id' => $id]),
                    'label' => $this->translator->trans($texts.'.back'),
                ]),
            ]);

        if ([] === $rows) {
            return $layout->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans($texts.'.empty.title'),
                'message' => $this->translator->trans($texts.'.empty.message'),
            ]);
        }

        return $layout
            ->withMain('Shared:ResourceList', [
                'label' => $this->translator->trans($texts.'.list', ['%name%' => $name]),
            ], $rows)
            ->withMain('Shared:Pagination', ($this->listPagination)('cms_relations', ['type' => $section, 'id' => $id], $page, $users->hasMore));
    }
}
