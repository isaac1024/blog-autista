<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\Page as PageAggregate;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\Title;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Ui\CmsLayout;
use Shared\Ui\Page\Page;
use Shared\Ui\Page\PageComponent;
use Shared\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageEditController extends AbstractController
{
    private const string DELETE_PAGE_MODAL = 'delete-page';

    private const string FORM = 'page-form';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly TiptapRichText $tiptapRichText,
        private readonly PageRole $pageRole,
    ) {}

    #[Route('/pages/{id}', name: 'cms_pages_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, PageRepository $pageRepository): Response
    {
        try {
            $page = new PageFinder($id)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render($this->page($page));
    }

    private function page(PageResponse $page): Page
    {
        return CmsLayout::page(\sprintf(
            '%s · %s · blog autista',
            $page->title,
            $this->translator->trans('cms.pages.edit.title'),
        ))
            // The document comes first: its title, edited where it reads, then its address and content.
            ->withMain('Shared:DocumentTitle', [
                'name' => 'title',
                'id' => 'page-title',
                'label' => $this->translator->trans('cms.pages.edit.title_label'),
                'value' => $page->title,
                'form' => self::FORM,
                'badge' => ($this->pageRole)($page),
                'attributes' => [
                    'required' => true,
                    'minlength' => Title::MIN_LENGTH,
                    'maxlength' => Title::MAX_LENGTH,
                ],
            ], $page->unique ? [] : [$this->deleteModal($page)])
            ->withMain('Shared:Form', [
                'action' => $this->generateUrl('cms_pages_update', ['id' => $page->id]),
                'method' => 'post',
                'id' => self::FORM,
            ], [
                // The home page is served at the root of the site, so it has no slug to edit.
                $page->home ? $this->homeAddress() : $this->slugField($page),
                new PageComponent('Shared:RichTextEditor', [
                    'name' => 'content',
                    'id' => 'page-content-editor',
                    'label' => $this->translator->trans('cms.pages.edit.content_title'),
                    'placeholder' => $this->translator->trans('cms.pages.edit.content_placeholder'),
                    'value' => json_encode($this->tiptapRichText->toTiptap($page->content), \JSON_THROW_ON_ERROR),
                    'features' => PageAggregate::contentFeatures()->toArray(),
                ]),
                new PageComponent('Shared:ActionBar', [], [
                    new PageComponent('Shared:FormButton', [
                        'label' => $this->translator->trans('cms.pages.edit.submit'),
                    ]),
                ]),
            ]);
    }

    private function homeAddress(): PageComponent
    {
        return new PageComponent('Shared:FormHint', [
            'id' => 'page-home-address',
            'text' => $this->translator->trans('cms.pages.edit.home_address'),
        ]);
    }

    /**
     * Only generic pages can be deleted; unique pages stay for good.
     */
    private function deleteModal(PageResponse $page): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => self::DELETE_PAGE_MODAL,
            'trigger' => $this->translator->trans('cms.pages.delete.trigger'),
            'title' => $this->translator->trans('cms.pages.delete.title'),
            'description' => $this->translator->trans('cms.pages.delete.description', ['%title%' => $page->title]),
            'variant' => 'danger',
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->generateUrl('cms_pages_delete', ['id' => $page->id]),
                'method' => 'post',
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.pages.delete.submit'),
                    'variant' => 'danger',
                ]),
            ]),
        ]);
    }

    private function slugField(PageResponse $page): PageComponent
    {
        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'page-slug',
                'text' => $this->translator->trans('cms.pages.edit.slug_label'),
            ]),
            new PageComponent('Shared:FormHint', [
                'id' => 'page-slug-hint',
                'text' => $this->translator->trans('cms.pages.edit.slug_hint'),
            ]),
            new PageComponent('Shared:FormInput', [
                'name' => 'slug',
                'id' => 'page-slug',
                'value' => $page->slug,
                'prefix' => '/',
                'suffix' => '.html',
                'attributes' => [
                    'required' => true,
                    'minlength' => Slug::MIN_LENGTH,
                    'maxlength' => Slug::MAX_LENGTH,
                    'pattern' => Slug::SHAPE,
                    'autocapitalize' => 'none',
                    'spellcheck' => 'false',
                    'aria-describedby' => 'page-slug-hint',
                ],
            ]),
        ]);
    }
}
