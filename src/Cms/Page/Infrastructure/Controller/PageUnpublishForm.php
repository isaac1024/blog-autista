<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageResponse;
use Cms\Redirect\Domain\Resource;
use Cms\Shared\Infrastructure\Publication\RetirementForm;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where a page is taken off the site: the page it is, a way back
 * to its editor, and the form that asks what becomes of its addresses.
 */
final readonly class PageUnpublishForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RetirementForm $retirementForm,
    ) {}

    /**
     * @param array<array-key, mixed> $saved what was sent the last time, when it was refused, to choose it again
     */
    public function __invoke(PageResponse $page, array $saved): Page
    {
        $title = $this->translator->trans('cms.pages.unpublish.page_title', ['%title%' => $page->title]);

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.retire.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_pages_edit', ['id' => $page->id]),
                    'label' => $this->translator->trans('cms.pages.unpublish.back'),
                ]),
            ]);

        foreach (($this->retirementForm)(
            Resource::page($page->id),
            $this->urlGenerator->generate('cms_pages_unpublish', ['id' => $page->id]),
            'cms_pages_unpublish',
            $this->translator->trans('cms.pages.unpublish.submit'),
            $saved,
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $layout;
    }
}
