<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Post\Application\PostResponse;
use Cms\Redirect\Domain\Resource;
use Cms\Shared\Infrastructure\Publication\RetirementForm;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where a post is taken off the site: the post it is, a way back
 * to its editor, and the form that asks what becomes of its addresses.
 */
final readonly class PostUnpublishForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RetirementForm $retirementForm,
    ) {}

    /**
     * @param array<array-key, mixed> $saved what was sent the last time, when it was refused, to choose it again
     */
    public function __invoke(PostResponse $post, array $saved): Page
    {
        $title = $this->translator->trans('cms.posts.unpublish.page_title', ['%title%' => $post->title]);

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.retire.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_posts_edit', ['id' => $post->id]),
                    'label' => $this->translator->trans('cms.posts.unpublish.back'),
                ]),
            ]);

        foreach (($this->retirementForm)(
            Resource::post($post->id),
            $this->urlGenerator->generate('cms_posts_unpublish', ['id' => $post->id]),
            'cms_posts_unpublish',
            $this->translator->trans('cms.posts.unpublish.submit'),
            $saved,
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $layout;
    }
}
