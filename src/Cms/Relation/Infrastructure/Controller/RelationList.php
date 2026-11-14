<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Redirect\Infrastructure\Controller\IncomingRedirectsPanel;
use Cms\Relation\Application\UsersResponse;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where a resource of the CMS is used: every resource that relates
 * to it, each leading to where it is edited, apart from the editor
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
    public function __invoke(RelationSegment $type, string $id, string $name, UsersResponse $users, int $page): Page
    {
        // Where the resource is edited in the CMS.
        [$editor, $parameters] = $type->editor($id);
        $rows = ($this->userRows)($users->users);

        $layout = CmsLayout::page(\sprintf(
            '%s · %s · CMS',
            $name,
            $this->translator->trans('cms.relations.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.relations.title'),
                'subtitle' => $this->translator->trans($type->key('subtitle'), ['%name%' => $name]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate($editor, $parameters),
                    'label' => $this->translator->trans($type->key('back')),
                ]),
            ]);

        $layout = [] === $rows
            ? $layout->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans($type->key('empty.title')),
                'message' => $this->translator->trans($type->key('empty.message')),
            ])
            : $layout
                ->withMain('Shared:ResourceList', [
                    'label' => $this->translator->trans($type->key('list'), ['%name%' => $name]),
                ], $rows)
                ->withMain('Shared:Pagination', ($this->listPagination)('cms_relations', ['type' => $type->value, 'id' => $id], $page, $users->hasMore));

        // The redirects that lead to it keep it on the site, as what uses it
        // keeps it in the library.
        $panel = ($this->incomingRedirectsPanel)($type->redirectType(), $id, $name);

        return $layout->withMain($panel->name, $panel->props, $panel->children);
    }
}
