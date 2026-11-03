<?php

declare(strict_types=1);

namespace Cms\Page\Application;

use Cms\Page\Domain\InvalidPageType;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Domain\SlugAvailability;
use Cms\Page\Domain\UniquePageAlreadyExists;

/**
 * Creates one of the unique pages of the site, failing when it already exists.
 */
final readonly class UniquePageCreator
{
    public function __construct(
        private string $id,
        private string $type,
        private string $title,
    ) {}

    public function __invoke(PageRepository $pageRepository): void
    {
        $type = PageType::tryFrom($this->type) ?? throw InvalidPageType::unknown($this->type);
        $page = Page::createUnique($this->id, $type, $this->title);

        if (null !== $pageRepository->findByType($type)) {
            throw UniquePageAlreadyExists::ofType($type);
        }

        new SlugAvailability($page)($pageRepository);
        $page->save($pageRepository);
    }
}
