<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Redirect\Domain\Resource;
use Cms\Shared\Infrastructure\Publication\RetirementForm;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes where an author is taken off the site: the author it is, a way back
 * to its editor, and the form that asks what becomes of its addresses.
 */
final readonly class AuthorUnpublishForm
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private RetirementForm $retirementForm,
    ) {}

    /**
     * @param array<array-key, mixed> $saved what was sent the last time, when it was refused, to choose it again
     */
    public function __invoke(AuthorResponse $author, array $saved): Page
    {
        $title = $this->translator->trans('cms.authors.unpublish.page_title', ['%name%' => $author->name]);

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.retire.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->urlGenerator->generate('cms_authors_edit', ['id' => $author->id]),
                    'label' => $this->translator->trans('cms.authors.unpublish.back'),
                ]),
            ]);

        foreach (($this->retirementForm)(
            Resource::author($author->id),
            $this->urlGenerator->generate('cms_authors_unpublish', ['id' => $author->id]),
            'cms_authors_unpublish',
            $this->translator->trans('cms.authors.unpublish.submit'),
            $saved,
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $layout;
    }
}
