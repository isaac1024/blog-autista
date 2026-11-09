<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Page\Application\PageResponse;
use Cms\Page\Application\PagesFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Infrastructure\Controller\PageRole;
use Cms\Page\Infrastructure\Controller\PageState;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The pages that use a resource, as rows like those of the list of pages.
 */
final readonly class PageUserRows
{
    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private PageRepository $pageRepository,
        private PageState $pageState,
        private PageRole $pageRole,
    ) {}

    /**
     * @param list<string> $ids
     *
     * @return list<PageComponent>
     */
    public function __invoke(array $ids): array
    {
        return array_map($this->row(...), new PagesFinder($ids)($this->pageRepository));
    }

    private function row(PageResponse $page): PageComponent
    {
        return new PageComponent('Shared:ResourceRow', [
            'title' => $page->title,
            'url' => $this->urlGenerator->generate('cms_pages_edit', ['id' => $page->id]),
            'meta' => \sprintf('%s · %s', $this->translator->trans('cms.relations.types.page'), $this->pageState->lastUpdate($page)),
            'badge' => ($this->pageRole)($page),
            'status' => $this->pageState->label($page),
        ]);
    }
}
