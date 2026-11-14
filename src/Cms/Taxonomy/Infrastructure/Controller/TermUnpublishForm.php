<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Controller;

use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Infrastructure\Controller\RetirementForm;
use Cms\Taxonomy\Application\TermResponse;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where a term is taken off the site: the term it is, a way back
 * to its editor, and the form that asks what becomes of its addresses.
 */
final readonly class TermUnpublishForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RetirementForm $retirementForm,
    ) {}

    /**
     * @param array<array-key, mixed> $saved what was sent the last time, when it was refused, to choose it again
     */
    public function __invoke(TaxonomySegment $taxonomy, TermResponse $term, array $saved): Page
    {
        $title = $this->translator->trans($taxonomy->key('unpublish.page_title'), ['%name%' => $term->name]);
        $parameters = ['taxonomy' => $taxonomy->value, 'id' => $term->id];

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.retire.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_terms_edit', $parameters),
                    'label' => $this->translator->trans($taxonomy->key('unpublish.back')),
                ]),
            ]);

        foreach (($this->retirementForm)(
            Resource::term($term->id),
            $this->urlGenerator->generate('cms_terms_unpublish', $parameters),
            'cms_terms_unpublish',
            $this->translator->trans($taxonomy->key('unpublish.submit')),
            $saved,
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $layout;
    }
}
