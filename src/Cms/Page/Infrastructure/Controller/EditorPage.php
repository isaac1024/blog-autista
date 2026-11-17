<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageResponse;
use Cms\Shared\Infrastructure\Ui\PageState;

/**
 * A page as the rich text editor shows it, to link to it or once linked:
 * its title and where it stands for the site.
 */
final readonly class EditorPage
{
    public function __construct(private PageState $pageState) {}

    /**
     * @return array{id: string, title: string, status: string, published: bool}
     */
    public function __invoke(PageResponse $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'status' => $this->pageState->label($page),
            'published' => 'published' === $page->status,
        ];
    }
}
