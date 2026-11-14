<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Application\AuthorsResponse;
use Cms\Author\Domain\AuthorName;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the list of authors, newest first, searched by their name, with a
 * way to create one, and what to do when there is none to list.
 */
final readonly class AuthorList
{
    private const string NEW_AUTHOR_MODAL = 'new-author';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private AuthorState $authorState,
        private ListPagination $listPagination,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(AuthorsResponse $found, string $search, int $number): Page
    {
        // The search box keeps what was searched, as the search took it.
        $name = trim($search);
        $page = CmsLayout::page(\sprintf(
            '%s · CMS',
            $this->translator->trans('cms.authors.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.authors.title'),
                'subtitle' => $this->translator->trans('cms.authors.subtitle'),
            ], [
                $this->newAuthorModal(),
            ])
            ->withMain('Shared:SearchForm', [
                'action' => $this->urlGenerator->generate('cms_authors'),
                'name' => 'name',
                'id' => 'author-search',
                'label' => $this->translator->trans('cms.authors.search.label'),
                'submit' => $this->translator->trans('cms.authors.search.submit'),
                'value' => $name,
            ]);

        if ([] === $found->authors && '' === $name) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.authors.empty.title'),
                'message' => $this->translator->trans('cms.authors.empty.message'),
            ], [
                new PageComponent('Shared:ModalTrigger', [
                    'target' => self::NEW_AUTHOR_MODAL,
                    'label' => $this->translator->trans('cms.authors.empty.action'),
                ]),
            ]);
        }

        if ([] === $found->authors) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.authors.search.empty.title'),
                'message' => $this->translator->trans('cms.authors.search.empty.message', ['%name%' => $name]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_authors'),
                    'label' => $this->translator->trans('cms.authors.search.empty.action'),
                ]),
            ]);
        }

        return $page->withMain('Shared:ResourceList', [
            'label' => $this->translator->trans('cms.authors.list.label'),
        ], array_map($this->row(...), $found->authors))
            ->withMain('Shared:Pagination', ($this->listPagination)(
                'cms_authors',
                '' === $name ? [] : ['name' => $name],
                $number,
                $found->hasMore,
            ));
    }

    /**
     * An author with its address, when it last changed and where it stands.
     * A published author shows the address it has on the site, which changes
     * only when it is published again.
     */
    private function row(AuthorResponse $author): PageComponent
    {
        $slug = $author->publishedSlug ?? $author->slug;

        return new PageComponent('Shared:ResourceRow', [
            'title' => $author->name,
            'url' => $this->urlGenerator->generate('cms_authors_edit', ['id' => $author->id]),
            'meta' => \sprintf(
                '%s · %s',
                null === $slug ? $this->translator->trans('cms.authors.list.no_slug') : $this->urlGenerator->generate('web_author', ['slug' => $slug]),
                $this->authorState->lastUpdate($author),
            ),
            'status' => $this->authorState->label($author),
        ]);
    }

    /**
     * The name of the new author, which its address derives from.
     */
    private function newAuthorModal(): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => self::NEW_AUTHOR_MODAL,
            'trigger' => $this->translator->trans('cms.authors.add'),
            'title' => $this->translator->trans('cms.authors.modal.title'),
            'description' => $this->translator->trans('cms.authors.modal.description'),
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_authors_create'),
                'method' => 'post',
                'csrfTokenId' => 'cms_authors_create',
            ], [
                new PageComponent('Shared:FormField', [], [
                    new PageComponent('Shared:FormLabel', [
                        'for' => 'author-name',
                        'text' => $this->translator->trans('cms.authors.name_label'),
                    ]),
                    new PageComponent('Shared:FormInput', [
                        'name' => 'name',
                        'id' => 'author-name',
                        'attributes' => [
                            'required' => true,
                            'minlength' => AuthorName::MIN_LENGTH,
                            'maxlength' => AuthorName::MAX_LENGTH,
                            'autofocus' => true,
                        ],
                    ]),
                ]),
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.authors.modal.submit'),
                ]),
            ]),
        ]);
    }
}
