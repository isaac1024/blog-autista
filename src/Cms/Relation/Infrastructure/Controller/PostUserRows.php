<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Cms\Post\Application\PostsFinder;
use Cms\Post\Domain\PostRepository;
use Cms\Shared\Infrastructure\Ui\PostState;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The posts that use a resource, each as a row that opens its editor; a post
 * gone meanwhile is left out.
 */
final readonly class PostUserRows
{
    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private PostRepository $postRepository,
        private PostState $postState,
    ) {}

    /**
     * @param list<string> $ids
     *
     * @return list<PageComponent>
     */
    public function __invoke(array $ids): array
    {
        return array_map($this->row(...), new PostsFinder($ids)($this->postRepository));
    }

    private function row(PostResponse $post): PageComponent
    {
        return new PageComponent('Shared:ResourceRow', [
            'title' => $post->title,
            'url' => $this->urlGenerator->generate('cms_posts_edit', ['id' => $post->id]),
            'meta' => \sprintf('%s · %s', $this->translator->trans('cms.relations.types.post'), $this->postState->lastUpdate($post)),
            'status' => $this->postState->label($post),
        ]);
    }
}
