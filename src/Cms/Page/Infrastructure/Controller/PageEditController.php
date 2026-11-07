<?php

declare(strict_types=1);

namespace Cms\Page\Infrastructure\Controller;

use Cms\Page\Application\PageFinder;
use Cms\Page\Application\PageNotFound;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\Page as PageAggregate;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageStatus;
use Cms\Page\Domain\Robots;
use Cms\Page\Domain\Seo;
use Cms\Page\Domain\Slug;
use Cms\Page\Domain\Title;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Twig\Components\SearchPreview;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composing a page reaches every component it is made of, so its coupling
 * grows with the page and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final class PageEditController extends AbstractController
{
    private const string DELETE_PAGE_MODAL = 'delete-page';

    private const string UNPUBLISH_PAGE_MODAL = 'unpublish-page';

    private const string DISCARD_CHANGES_MODAL = 'discard-changes';

    private const string FORM = 'page-form';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly TiptapRichText $tiptapRichText,
        private readonly PageRole $pageRole,
        private readonly PageState $pageState,
        #[Autowire('%env(WEB_HOST)%')]
        private readonly string $webHost,
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

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
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
                'status' => $this->pageState->label($page),
                // The title of the page is also its title in search results, cut around there.
                'recommendedMax' => SearchPreview::TITLE_LIMIT,
                'attributes' => [
                    'required' => true,
                    'minlength' => Title::MIN_LENGTH,
                    'maxlength' => Title::MAX_LENGTH,
                ],
            ], $this->pageActions($page))
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
                $this->searchEngineSection($page),
                new PageComponent('Shared:ActionBar', [
                    'status' => $this->pageState->description($page),
                ], $this->formActions($page)),
            ]);
    }

    /**
     * What is done to the page apart from editing it, each confirmed first:
     * discarding its unpublished changes, taking it off the site, or deleting
     * it once it is off. Unique pages are never taken off nor deleted.
     *
     * @return list<PageComponent>
     */
    private function pageActions(PageResponse $page): array
    {
        $published = PageStatus::Published->value === $page->status;
        $actions = [];

        if ($page->changed) {
            $actions[] = $this->confirmModal($page, self::DISCARD_CHANGES_MODAL, 'discard', 'cms_pages_discard', 'secondary');
        }

        if ($published && !$page->unique) {
            $actions[] = $this->confirmModal($page, self::UNPUBLISH_PAGE_MODAL, 'unpublish', 'cms_pages_unpublish', 'secondary');
        }

        if (!$published && !$page->unique) {
            $actions[] = $this->confirmModal($page, self::DELETE_PAGE_MODAL, 'delete', 'cms_pages_delete', 'danger');
        }

        return $actions;
    }

    /**
     * Saving comes first, so it is what Enter in a field does. Publishing
     * submits the same form, so what is in it goes on the site, saved or not;
     * a published page offers it again only once it has changes to publish.
     *
     * @return list<PageComponent>
     */
    private function formActions(PageResponse $page): array
    {
        $published = PageStatus::Published->value === $page->status;
        $publishable = !$published || $page->changed;
        $actions = [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.pages.edit.submit'),
                'variant' => $publishable ? 'secondary' : 'primary',
            ]),
        ];

        if ($publishable) {
            $actions[] = new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans($published ? 'cms.pages.edit.republish' : 'cms.pages.edit.publish'),
                'formaction' => $this->generateUrl('cms_pages_publish', ['id' => $page->id]),
            ]);
        }

        return $actions;
    }

    private function homeAddress(): PageComponent
    {
        return new PageComponent('Shared:FormHint', [
            'id' => 'page-home-address',
            'text' => $this->translator->trans('cms.pages.edit.home_address'),
        ]);
    }

    /**
     * A modal that confirms an action on the page, with its texts under
     * cms.pages.{action}.
     */
    private function confirmModal(PageResponse $page, string $id, string $action, string $route, string $variant): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => $id,
            'trigger' => $this->translator->trans(\sprintf('cms.pages.%s.trigger', $action)),
            'title' => $this->translator->trans(\sprintf('cms.pages.%s.title', $action)),
            'description' => $this->translator->trans(\sprintf('cms.pages.%s.description', $action), ['%title%' => $page->title]),
            'variant' => $variant,
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->generateUrl($route, ['id' => $page->id]),
                'method' => 'post',
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans(\sprintf('cms.pages.%s.submit', $action)),
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

    /**
     * How the page shows in search results, previewed above the settings that
     * shape it, so every change is seen where it lands.
     */
    private function searchEngineSection(PageResponse $page): PageComponent
    {
        $baseUrl = 'https://'.$this->webHost;

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.pages.edit.seo.title'),
            'description' => $this->translator->trans('cms.pages.edit.seo.description'),
            'variant' => 'disclosure',
        ], [
            new PageComponent('Shared:SearchPreview', [
                'label' => $this->translator->trans('cms.pages.edit.seo.preview'),
                'title' => $page->title,
                'url' => $page->home ? $baseUrl.'/' : \sprintf('%s/%s.html', $baseUrl, $page->slug),
                'baseUrl' => $baseUrl,
                'description' => $page->metaDescription,
                'emptyDescription' => $this->translator->trans('cms.pages.edit.seo.preview_empty'),
                'sources' => $page->home
                    ? ['title' => 'page-title', 'description' => 'page-meta-description']
                    : ['title' => 'page-title', 'description' => 'page-meta-description', 'slug' => 'page-slug'],
            ]),
            new PageComponent('Shared:FormField', [], [
                new PageComponent('Shared:FormLabel', [
                    'for' => 'page-meta-description',
                    'text' => $this->translator->trans('cms.pages.edit.seo.meta_description_label'),
                ]),
                new PageComponent('Shared:FormHint', [
                    'id' => 'page-meta-description-hint',
                    'text' => $this->translator->trans('cms.pages.edit.seo.meta_description_hint'),
                ]),
                new PageComponent('Shared:FormTextarea', [
                    'name' => 'meta_description',
                    'id' => 'page-meta-description',
                    'value' => $page->metaDescription,
                    'recommendedMin' => Seo::DESCRIPTION_RECOMMENDED_MIN_LENGTH,
                    'recommendedMax' => Seo::DESCRIPTION_RECOMMENDED_MAX_LENGTH,
                    'attributes' => [
                        'maxlength' => Seo::DESCRIPTION_MAX_LENGTH,
                        'aria-describedby' => 'page-meta-description-hint page-meta-description-count',
                    ],
                ]),
            ]),
            new PageComponent('Shared:FormRadioGroup', [
                'name' => 'robots',
                'id' => 'page-robots',
                'legend' => $this->translator->trans('cms.pages.edit.seo.robots_legend'),
                'value' => $page->robots,
                'options' => array_map(fn (Robots $robots): array => [
                    'value' => $robots->value,
                    'label' => $this->translator->trans('cms.pages.edit.seo.robots.'.$robots->value.'.label'),
                    'hint' => $this->translator->trans('cms.pages.edit.seo.robots.'.$robots->value.'.hint'),
                ], Robots::cases()),
            ]),
        ]);
    }
}
