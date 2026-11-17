<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Cms\Page\Application\PageResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Names the role a unique page fills, such as the home page, so editors can
 * tell it apart; null for generic pages, and when the title already says it.
 */
final readonly class PageRole
{
    public function __construct(private TranslatorInterface $translator) {}

    public function __invoke(PageResponse $page): ?string
    {
        if (!$page->unique) {
            return null;
        }

        $role = $this->translator->trans('cms.pages.types.'.$page->type);

        return mb_strtolower($role) === mb_strtolower($page->title) ? null : $role;
    }
}
