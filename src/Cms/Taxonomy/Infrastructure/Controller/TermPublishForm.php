<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Redirect\Application\PublicationOutlook;
use Cms\Shared\Infrastructure\Publication\PublicationForm;
use Cms\Taxonomy\Application\TermResponse;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where publishing a term is confirmed when it changes the
 * redirects: the term it is, a way back to its editor, and the form that
 * tells and asks what publishing does to them.
 */
final readonly class TermPublishForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private PublicationForm $publicationForm,
    ) {}

    /**
     * @param array<array-key, mixed> $saved what was sent the last time, when it was refused, to choose it again
     */
    public function __invoke(TaxonomySegment $taxonomy, TermResponse $term, PublicationOutlook $outlook, array $saved): Page
    {
        $title = $this->translator->trans($taxonomy->key('publish.page_title'), ['%name%' => $term->name]);
        $parameters = ['taxonomy' => $taxonomy->value, 'id' => $term->id];

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.publish.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_terms_edit', $parameters),
                    'label' => $this->translator->trans($taxonomy->key('unpublish.back')),
                ]),
            ]);

        foreach (($this->publicationForm)(
            $outlook,
            $this->urlGenerator->generate('cms_terms_publish', $parameters),
            'cms_terms_publish',
            $this->translator->trans($taxonomy->key('publish.submit')),
            $saved,
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $layout;
    }
}
