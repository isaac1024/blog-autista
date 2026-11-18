<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Shared\Infrastructure\Ui\TermState;
use Cms\Taxonomy\Application\TermResponse;
use Cms\Taxonomy\Application\TermsResponse;
use Cms\Taxonomy\Domain\TermName;
use Shared\Domain\SiteTimeZone;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the list of the terms of a taxonomy, newest first, searched by
 * their name, with a way to create one, and what to do when there is none to
 * list.
 */
final readonly class TermList
{
    private const string NEW_TERM_MODAL = 'new-term';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private TermState $termState,
        private TermAddress $termAddress,
        private ListPagination $listPagination,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(TaxonomySegment $taxonomy, TermsResponse $found, string $search, int $number): Page
    {
        // The search box keeps what was searched, as the search took it.
        $name = trim($search);
        $page = CmsLayout::page(\sprintf(
            '%s · CMS',
            $this->translator->trans($taxonomy->key('title')),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans($taxonomy->key('title')),
                'subtitle' => $this->translator->trans($taxonomy->key('subtitle')),
            ], [
                $this->newTermModal($taxonomy),
            ])
            ->withMain('Shared:SearchForm', [
                'action' => $this->urlGenerator->generate('cms_terms', ['taxonomy' => $taxonomy->value]),
                'name' => 'name',
                'id' => 'term-search',
                'label' => $this->translator->trans('cms.terms.search.label'),
                'submit' => $this->translator->trans('cms.terms.search.submit'),
                'value' => $name,
            ]);

        if ([] === $found->terms && '' === $name) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans($taxonomy->key('empty.title')),
                'message' => $this->translator->trans($taxonomy->key('empty.message')),
            ], [
                new PageComponent('Shared:ModalTrigger', [
                    'target' => self::NEW_TERM_MODAL,
                    'label' => $this->translator->trans($taxonomy->key('empty.action')),
                ]),
            ]);
        }

        if ([] === $found->terms) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.terms.search.empty.title'),
                'message' => $this->translator->trans($taxonomy->key('search_empty'), ['%name%' => $name]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_terms', ['taxonomy' => $taxonomy->value]),
                    'label' => $this->translator->trans($taxonomy->key('search_all')),
                ]),
            ]);
        }

        return $page->withMain('Shared:ResourceList', [
            'label' => $this->translator->trans($taxonomy->key('title')),
        ], array_map(fn (TermResponse $term): PageComponent => $this->row($taxonomy, $term), $found->terms))
            ->withMain('Shared:Pagination', ($this->listPagination)(
                'cms_terms',
                '' === $name ? ['taxonomy' => $taxonomy->value] : ['taxonomy' => $taxonomy->value, 'name' => $name],
                $number,
                $found->hasMore,
            ));
    }

    /**
     * A term with its address, when it last changed and where it stands. A
     * published term shows the address it has on the site, which changes only
     * when it is published again.
     */
    private function row(TaxonomySegment $taxonomy, TermResponse $term): PageComponent
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, SiteTimeZone::NAME);
        $slug = $term->publishedSlug ?? $term->slug;

        return new PageComponent('Shared:ResourceRow', [
            'title' => $term->name,
            'url' => $this->urlGenerator->generate('cms_terms_edit', ['taxonomy' => $taxonomy->value, 'id' => $term->id]),
            'meta' => \sprintf(
                '%s · %s',
                null === $slug ? $this->translator->trans('cms.terms.list.no_slug') : $this->termAddress->address($taxonomy, $slug),
                $this->translator->trans('cms.terms.list.updated_at', ['%date%' => $formatter->format($term->updatedAt)]),
            ),
            'status' => $this->termState->label($term),
        ]);
    }

    /**
     * The name of the new term, which its address derives from.
     */
    private function newTermModal(TaxonomySegment $taxonomy): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => self::NEW_TERM_MODAL,
            'trigger' => $this->translator->trans($taxonomy->key('add')),
            'title' => $this->translator->trans($taxonomy->key('modal.title')),
            'description' => $this->translator->trans($taxonomy->key('modal.description')),
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_terms_create', ['taxonomy' => $taxonomy->value]),
                'method' => 'post',
                'csrfTokenId' => 'cms_terms_create',
            ], [
                new PageComponent('Shared:FormField', [], [
                    new PageComponent('Shared:FormLabel', [
                        'for' => 'term-name',
                        'text' => $this->translator->trans('cms.terms.name_label'),
                    ]),
                    new PageComponent('Shared:FormInput', [
                        'name' => 'name',
                        'id' => 'term-name',
                        'attributes' => [
                            'required' => true,
                            'minlength' => TermName::MIN_LENGTH,
                            'maxlength' => TermName::MAX_LENGTH,
                            'autofocus' => true,
                        ],
                    ]),
                ]),
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans($taxonomy->key('modal.submit')),
                ]),
            ]),
        ]);
    }
}
