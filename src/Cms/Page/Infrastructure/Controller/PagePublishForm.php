<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageResponse;
use Cms\Redirect\Application\PublicationOutlook;
use Cms\Redirect\Infrastructure\Controller\PublicationForm;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where publishing a page is confirmed when it changes the
 * redirects: the page it is, a way back to its editor, and the form that
 * tells and asks what publishing does to them.
 */
final readonly class PagePublishForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private PublicationForm $publicationForm,
    ) {}

    /**
     * @param array<array-key, mixed> $saved what was sent the last time, when it was refused, to choose it again
     */
    public function __invoke(PageResponse $page, PublicationOutlook $outlook, array $saved): Page
    {
        $title = $this->translator->trans('cms.pages.publish.page_title', ['%title%' => $page->title]);

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.publish.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_pages_edit', ['id' => $page->id]),
                    'label' => $this->translator->trans('cms.pages.unpublish.back'),
                ]),
            ]);

        foreach (($this->publicationForm)(
            $outlook,
            $this->urlGenerator->generate('cms_pages_publish', ['id' => $page->id]),
            'cms_pages_publish',
            $this->translator->trans('cms.pages.publish.submit'),
            $saved,
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $layout;
    }
}
