<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Redirect\Application\PublicationOutlook;
use Cms\Shared\Infrastructure\Publication\PublicationForm;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where publishing an author is confirmed when it changes the
 * redirects: the author it is, a way back to its editor, and the form that
 * tells and asks what publishing does to them.
 */
final readonly class AuthorPublishForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private PublicationForm $publicationForm,
    ) {}

    /**
     * @param array<array-key, mixed> $saved what was sent the last time, when it was refused, to choose it again
     */
    public function __invoke(AuthorResponse $author, PublicationOutlook $outlook, array $saved): Page
    {
        $title = $this->translator->trans('cms.authors.publish.page_title', ['%name%' => $author->name]);

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.publish.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_authors_edit', ['id' => $author->id]),
                    'label' => $this->translator->trans('cms.authors.unpublish.back'),
                ]),
            ]);

        foreach (($this->publicationForm)(
            $outlook,
            $this->urlGenerator->generate('cms_authors_publish', ['id' => $author->id]),
            'cms_authors_publish',
            $this->translator->trans('cms.authors.publish.submit'),
            $saved,
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $layout;
    }
}
