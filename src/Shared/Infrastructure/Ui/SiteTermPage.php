<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\Page\Page;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Paints the page of a term of a taxonomy, such as a section or a tag, as the
 * site does, in the layout it is given: its name as the main heading, then
 * its posts as cards, newest first, a page of them at a time. The site serves
 * it this way, and the CMS previews it the same.
 *
 * @phpstan-import-type CardPost from PostCardsComponent
 */
final readonly class SiteTermPage
{
    public function __construct(
        private PostCardsComponent $postCards,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param list<CardPost>                                                                                                           $posts      those of the page shown
     * @param ?array{label: string, current: string, previousUrl: ?string, previousLabel: string, nextUrl: ?string, nextLabel: string} $pagination null where the list isn't paged, as in a preview
     */
    public function __invoke(Page $layout, string $name, array $posts, ?array $pagination): Page
    {
        // A list of cards, not a text to read: as wide as three of them.
        $page = $layout->withMainClass(WebLayout::WIDE_MAIN_CLASS)->withMain('Shared:PageTitle', ['title' => $name]);

        if ([] === $posts) {
            return $page->withMain('Shared:Notice', ['message' => $this->translator->trans('site.posts.empty')]);
        }

        $cards = ($this->postCards)($posts, 2, true);
        $page = $page->withMain($cards->name, $cards->props);

        return null === $pagination ? $page : $page->withMain('Shared:Pagination', $pagination);
    }
}
