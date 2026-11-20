<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Cms\Post\Application\PostsResponse;
use Cms\Post\Domain\PostTitle;
use Cms\Shared\Infrastructure\Ui\PostState;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the list of posts, newest first, searched by their title, with a
 * way to create one, and what to do when there is none to list.
 */
final readonly class PostList
{
    private const string NEW_POST_MODAL = 'new-post';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private PostState $postState,
        private PostUrl $postUrl,
        private ListPagination $listPagination,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(PostsResponse $found, string $search, int $number): Page
    {
        // The search box keeps what was searched, as the search took it.
        $title = trim($search);
        $page = CmsLayout::page(\sprintf(
            '%s · CMS',
            $this->translator->trans('cms.posts.title'),
        ))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.posts.title'),
                'subtitle' => $this->translator->trans('cms.posts.subtitle'),
            ], [
                $this->newPostModal(),
            ])
            ->withMain('Shared:SearchForm', [
                'action' => $this->urlGenerator->generate('cms_posts'),
                'name' => 'title',
                'id' => 'post-search',
                'label' => $this->translator->trans('cms.posts.search.label'),
                'submit' => $this->translator->trans('cms.posts.search.submit'),
                'value' => $title,
            ]);

        if ([] === $found->posts && '' === $title) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.posts.empty.title'),
                'message' => $this->translator->trans('cms.posts.empty.message'),
            ], [
                new PageComponent('Shared:ModalTrigger', [
                    'target' => self::NEW_POST_MODAL,
                    'label' => $this->translator->trans('cms.posts.empty.action'),
                ]),
            ]);
        }

        if ([] === $found->posts) {
            return $page->withMain('Shared:EmptyState', [
                'title' => $this->translator->trans('cms.posts.search.empty.title'),
                'message' => $this->translator->trans('cms.posts.search.empty.message', ['%title%' => $title]),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_posts'),
                    'label' => $this->translator->trans('cms.posts.search.empty.action'),
                ]),
            ]);
        }

        return $page->withMain('Shared:ResourceList', [
            'label' => $this->translator->trans('cms.posts.list.label'),
        ], $this->rows($found->posts))
            ->withMain('Shared:Pagination', ($this->listPagination)(
                'cms_posts',
                '' === $title ? [] : ['title' => $title],
                $number,
                $found->hasMore,
            ));
    }

    /**
     * Each post with its address on the site, while it has one, when it last
     * changed and where it stands. The address of a post off the site is
     * only known once it is published.
     *
     * @param list<PostResponse> $posts
     *
     * @return list<PageComponent>
     */
    private function rows(array $posts): array
    {
        $urls = $this->postUrl->allOnSite($posts);

        return array_map(fn (PostResponse $post): PageComponent => $this->row($post, $urls[$post->id]), $posts);
    }

    private function row(PostResponse $post, ?string $url): PageComponent
    {
        // The site's own path: a URL of another host comes whole, which a
        // narrow row would break into lines of host.
        $path = null === $url ? null : (string) parse_url($url, \PHP_URL_PATH);

        return new PageComponent('Shared:ResourceRow', [
            'title' => $post->title,
            'url' => $this->urlGenerator->generate('cms_posts_edit', ['id' => $post->id]),
            'meta' => \sprintf(
                '%s · %s',
                $path ?? $this->translator->trans('cms.posts.list.off_site'),
                $this->postState->lastUpdate($post),
            ),
            'status' => $this->postState->label($post),
        ]);
    }

    /**
     * The title of the new post, which its slug derives from.
     */
    private function newPostModal(): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => self::NEW_POST_MODAL,
            'trigger' => $this->translator->trans('cms.posts.add'),
            'title' => $this->translator->trans('cms.posts.modal.title'),
            'description' => $this->translator->trans('cms.posts.modal.description'),
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_posts_create'),
                'method' => 'post',
                'csrfTokenId' => 'cms_posts_create',
            ], [
                new PageComponent('Shared:FormField', [], [
                    new PageComponent('Shared:FormLabel', [
                        'for' => 'post-title',
                        'text' => $this->translator->trans('cms.posts.title_label'),
                    ]),
                    new PageComponent('Shared:FormInput', [
                        'name' => 'title',
                        'id' => 'post-title',
                        'attributes' => [
                            'required' => true,
                            'minlength' => PostTitle::MIN_LENGTH,
                            'maxlength' => PostTitle::MAX_LENGTH,
                            'autofocus' => true,
                        ],
                    ]),
                ]),
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.posts.modal.submit'),
                ]),
            ]),
        ]);
    }
}
