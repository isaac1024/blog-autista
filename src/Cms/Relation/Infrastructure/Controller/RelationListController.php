<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Author\Application\AuthorFinder;
use Cms\Author\Application\AuthorNotFound;
use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Application\PostFinder;
use Cms\Post\Application\PostNotFound;
use Cms\Post\Domain\PostRepository;
use Cms\Relation\Application\UsersFinder;
use Cms\Relation\Domain\RelationRepository;
use Cms\Taxonomy\Application\TermFinder;
use Cms\Taxonomy\Application\TermNotFound;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Shared\Domain\Criteria\InvalidCriteria;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Where a page, an image, a post, a section, a tag or an author is used:
 * every resource of the CMS that relates to it, each leading to where it is
 * edited, apart from the editor itself, and the redirects that lead to it.
 *
 * It finds the resource it lists the users of, so its coupling and what it
 * takes grow with the kinds of resource and say nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final class RelationListController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly RelationList $relationList,
        private readonly PageRepository $pageRepository,
        private readonly ImageRepository $imageRepository,
        private readonly PostRepository $postRepository,
        private readonly TermRepository $termRepository,
        private readonly AuthorRepository $authorRepository,
    ) {}

    /**
     * The type is the section of the CMS the resource is edited under.
     */
    #[Route('/{type}/{id}/relations', name: 'cms_relations', requirements: ['type' => RelationSegment::REQUIREMENT, 'id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(
        RelationSegment $type,
        string $id,
        RelationRepository $relationRepository,
        #[MapQueryParameter]
        int $page = ListPagination::FIRST_PAGE,
    ): Response {
        try {
            $name = $this->name($type, $id);
        } catch (PageNotFound|ImageNotFound|PostNotFound|TermNotFound|AuthorNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        try {
            $users = new UsersFinder($type->resourceType(), $id, $page)($relationRepository);
        } catch (InvalidCriteria $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->relationList)($type, $id, $name, $users, $page));
    }

    /**
     * How an editor names the resource: the title of a page or a post, the
     * alternative text of an image, the name of a term or an author.
     */
    private function name(RelationSegment $type, string $id): string
    {
        return match ($type) {
            RelationSegment::Pages => new PageFinder($id)($this->pageRepository)->title,
            RelationSegment::Images => new ImageFinder($id)($this->imageRepository)->alt,
            RelationSegment::Posts => new PostFinder($id)($this->postRepository)->title,
            RelationSegment::Sections => new TermFinder(Taxonomy::Section->value, $id)($this->termRepository)->name,
            RelationSegment::Tags => new TermFinder(Taxonomy::Tag->value, $id)($this->termRepository)->name,
            RelationSegment::Authors => new AuthorFinder($id)($this->authorRepository)->name,
        };
    }
}
