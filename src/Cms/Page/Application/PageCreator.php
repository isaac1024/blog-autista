<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;

/**
 * Creates a page from the submitted title, deriving its slug from it.
 */
final readonly class PageCreator
{
    public function __construct(
        private string $id,
        private string $title,
    ) {}

    public function __invoke(PageRepository $pageRepository): void
    {
        Page::create($this->id, $this->title)->save($pageRepository);
    }
}
