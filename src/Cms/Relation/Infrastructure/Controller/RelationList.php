<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Redirect\Domain\ResourceType as RedirectResourceType;
use Cms\Redirect\Infrastructure\Controller\IncomingRedirectsPanel;
use Cms\Relation\Application\UsersResponse;
use Cms\Relation\Domain\ResourceType;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where a page or an image is used: every resource of the CMS that
 * relates to it, each leading to where it is edited, apart from the editor
 * itself, and the redirects that lead to it.
 */
final readonly class RelationList
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private UserRows $userRows,
        private ListPagination $listPagination,
        private IncomingRedirectsPanel $incomingRedirectsPanel,
    ) {}

    /**
     * Composes the whole page from Twig components, so it takes what each of
     * them shows.
     */
    public function __invoke(ResourceType $type, string $id, string $name, UsersResponse $users, int $page): Page
    {
        // Where the resource is edited in the CMS.
        $section = ResourceType::Page === $type ? 'pages' : 'images';
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
                    'url' => $this->urlGenerator->generate(\sprintf('cms_%s_edit', $section), ['id' => $id]),
                    'label' => $this->translator->trans($texts.'.back'),
                ]),
            ]);

        $layout = [] === $rows
            ? $layout->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans($texts.'.empty.title'),
                'message' => $this->translator->trans($texts.'.empty.message'),
            ])
            : $layout
                ->withMain('Shared:ResourceList', [
                    'label' => $this->translator->trans($texts.'.list', ['%name%' => $name]),
                ], $rows)
                ->withMain('Shared:Pagination', ($this->listPagination)('cms_relations', ['type' => $section, 'id' => $id], $page, $users->hasMore));

        // The redirects that lead to it keep it on the site, as what uses it
        // keeps it in the library.
        $panel = ($this->incomingRedirectsPanel)(
            ResourceType::Page === $type ? RedirectResourceType::Page : RedirectResourceType::Image,
            $id,
            $name,
        );

        return $layout->withMain($panel->name, $panel->props, $panel->children);
    }
}
