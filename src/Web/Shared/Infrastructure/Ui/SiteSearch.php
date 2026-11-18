<?php

declare(strict_types=1);

namespace Web\Shared\Infrastructure\Ui;

use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\PostCardsComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Web\Post\Application\PostSearcher;
use Web\Post\Domain\PostRepository;

/**
 * Adds to the page that searches the site its search: the field, then what
 * it found, as cards, the most relevant first, a page of them at a time. It
 * says so when nothing was found, when no word is long enough to search by,
 * and when the words are so common it would find too many, asking for
 * another. Nothing searched is kept.
 *
 * @throws \Web\Post\Application\PostsPageNotFound for a page of results that can't be
 */
final readonly class SiteSearch
{
    /**
     * The query parameter that holds what is searched.
     */
    public const string PARAMETER = 'q';

    public function __construct(
        private PostRepository $postRepository,
        private SitePostCards $postCards,
        private PostCardsComponent $postCardsComponent,
        private ListPagination $pagination,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param string $slug the slug of the page that searches, where the results go
     */
    public function __invoke(Page $page, string $slug, string $text, int $number): Page
    {
        $found = new PostSearcher($text, $number)($this->postRepository);
        $page = $this->form($page, $slug, $text);

        if ($found->tooShort) {
            return $page->withMain('Shared:Notice', ['message' => $this->translator->trans('site.search.too_short', ['%min%' => PostSearcher::MIN_WORD_LENGTH])]);
        }

        if ($found->tooBroad) {
            return $page->withMain('Shared:Notice', ['message' => $this->translator->trans('site.search.too_broad', ['%text%' => trim($text)])]);
        }

        if (!$found->asked) {
            return $page;
        }

        if ([] === $found->posts) {
            return $page->withMain('Shared:Notice', ['message' => $this->translator->trans('site.search.none', ['%text%' => trim($text)])]);
        }

        $cards = ($this->postCardsComponent)(($this->postCards)($found->posts), 2, false);

        return $page
            ->withMain($cards->name, $cards->props)
            ->withMain('Shared:Pagination', $this->pagination->site('web_page', ['slug' => $slug, self::PARAMETER => $text], $number, $found->hasMore));
    }

    /**
     * The search, with what the reader wrote in its field, but not searched:
     * they searched more often than they may, and are asked to wait.
     *
     * @param string $slug the slug of the page that searches, where the results go
     */
    public function limited(Page $page, string $slug, string $text): Page
    {
        return $this->form($page, $slug, $text)->withMain('Shared:Notice', ['message' => $this->translator->trans('site.search.too_many')]);
    }

    private function form(Page $page, string $slug, string $text): Page
    {
        return $page->withMain('Shared:SearchForm', [
            'action' => $this->pagination->siteUrl('web_page', ['slug' => $slug], ListPagination::FIRST_PAGE, UrlGeneratorInterface::ABSOLUTE_PATH),
            'name' => self::PARAMETER,
            'id' => 'site-search',
            'label' => $this->translator->trans('site.search.label'),
            'submit' => $this->translator->trans('site.search.submit'),
            'value' => $text,
        ]);
    }
}
