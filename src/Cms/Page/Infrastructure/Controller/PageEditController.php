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
use Cms\Redirect\Infrastructure\Controller\LastAddressPanel;
use Shared\Domain\Clock;
use Shared\Infrastructure\Http\SignedUrls;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Twig\Components\SearchPreview;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    private const string DISCARD_CHANGES_MODAL = 'discard-changes';

    private const string FORM = 'page-form';

    private const string PUBLISH_FORM = 'page-publish-form';

    private const string PREVIEW_MODAL = 'preview';

    /**
     * How long a shared preview link lets anyone see the page.
     */
    private const string SHARED_PREVIEW_VALIDITY = 'P7D';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly TiptapRichText $tiptapRichText,
        private readonly PageRole $pageRole,
        private readonly PageState $pageState,
        private readonly LastAddressPanel $lastAddressPanel,
    ) {}

    #[Route('/pages/{id}', name: 'cms_pages_edit', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, PageRepository $pageRepository, SignedUrls $signedUrls): Response
    {
        try {
            $page = new PageFinder($id)($pageRepository);
        } catch (PageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        // Signed anew each time the editor loads, valid for a while from now.
        $expiresAt = Clock::now()->add(new \DateInterval(self::SHARED_PREVIEW_VALIDITY));
        $sharedPreview = new SharedPreviewLink(
            $signedUrls->sign($this->generateUrl('cms_pages_preview', ['id' => $page->id], UrlGeneratorInterface::ABSOLUTE_URL), $expiresAt),
            $expiresAt,
        );

        return $this->pageRenderer->render($this->page($page, $sharedPreview));
    }

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function page(PageResponse $page, SharedPreviewLink $sharedPreview): Page
    {
        return $this->lastAddressPanel->onPage(CmsLayout::page(\sprintf(
            '%s · %s · CMS',
            $page->title,
            $this->translator->trans('cms.pages.edit.title'),
        ))
            // What is being edited and where it stands, with what is done to it apart from editing.
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.pages.edit.title'),
                'subtitle' => $this->dates($page),
                'badge' => ($this->pageRole)($page),
                'status' => $this->pageState->label($page),
            ], $this->pageActions($page, $sharedPreview)), $page->id)
            ->withMain('Shared:Form', [
                'action' => $this->generateUrl('cms_pages_update', ['id' => $page->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_pages_update',
                'id' => self::FORM,
            ], [
                // The document comes first: its title, then its address and content.
                $this->titleField($page),
                // The home page is served at the root of the site, so it has no slug to edit.
                $page->home ? $this->homeAddress() : $this->slugField($page),
                new PageComponent('Shared:RichTextEditor', [
                    'name' => 'content',
                    'id' => 'page-content-editor',
                    'label' => $this->translator->trans('cms.pages.edit.content_title'),
                    'placeholder' => $this->translator->trans('cms.pages.edit.content_placeholder'),
                    'value' => json_encode($this->tiptapRichText->toTiptap($page->content), \JSON_THROW_ON_ERROR),
                    'features' => PageAggregate::contentFeatures()->toArray(),
                    'imageSearchUrl' => $this->generateUrl('cms_images_search'),
                    'imageLookupUrl' => $this->generateUrl('cms_images_lookup'),
                    // A page doesn't link itself, so the search leaves it out.
                    'pageSearchUrl' => $this->generateUrl('cms_pages_search', ['except' => $page->id]),
                    'pageLookupUrl' => $this->generateUrl('cms_pages_lookup'),
                ]),
                $this->searchEngineSection($page),
                new PageComponent('Shared:ActionBar', [
                    'status' => $this->pageState->description($page),
                ], $this->formActions($page)),
            ])
            // Publishing takes what is saved, not what the edit form holds, so
            // it is a form of its own, which its button in the bar submits.
            ->withMain('Shared:Form', [
                'action' => $this->generateUrl('cms_pages_publish', ['id' => $page->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_pages_publish',
                'id' => self::PUBLISH_FORM,
                'hidden' => true,
            ]);
    }

    /**
     * What is done to the page apart from editing it, offered only when the
     * page allows it: previewing what is saved, through a link that expires
     * and can be sent to anyone, seeing it on the site, seeing what uses it,
     * and, each confirmed first, discarding its unpublished changes, taking it
     * off the site, or deleting it.
     *
     * @return list<PageComponent>
     */
    private function pageActions(PageResponse $page, SharedPreviewLink $sharedPreview): array
    {
        $actions = [$this->previewModal($sharedPreview)];

        // The page as the site serves it, at the address it has there, which
        // may differ from the one being edited.
        if (PageStatus::Published->value === $page->status) {
            $actions[] = new PageComponent('Shared:ButtonLink', [
                'url' => $this->publishedUrl($page),
                'label' => $this->translator->trans('cms.pages.edit.view_on_site'),
            ]);
        }

        // What uses the page, on a page of its own.
        $actions[] = new PageComponent('Shared:ButtonLink', [
            'url' => $this->generateUrl('cms_relations', ['type' => 'pages', 'id' => $page->id]),
            'label' => $this->translator->trans('cms.pages.edit.relations'),
        ]);

        if ($page->changed) {
            $actions[] = $this->confirmModal($page, self::DISCARD_CHANGES_MODAL, 'discard', 'cms_pages_discard', 'secondary');
        }

        if ($page->canBeUnpublished) {
            // Taking it off the site asks what becomes of its addresses, on a
            // page of its own.
            $actions[] = new PageComponent('Shared:ButtonLink', [
                'url' => $this->generateUrl('cms_pages_unpublish_form', ['id' => $page->id]),
                'label' => $this->translator->trans('cms.pages.unpublish.trigger'),
            ]);
        }

        if ($page->canBeDeleted) {
            $actions[] = $this->confirmModal($page, self::DELETE_PAGE_MODAL, 'delete', 'cms_pages_delete', 'danger');
        }

        return $actions;
    }

    /**
     * The version last saved, as the site would serve it once published,
     * behind a signed link that shows it to anyone who has it, without
     * signing in, until it expires: the editor opens it, or copies it to send.
     */
    private function previewModal(SharedPreviewLink $sharedPreview): PageComponent
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT);

        return new PageComponent('Shared:Modal', [
            'id' => self::PREVIEW_MODAL,
            'trigger' => $this->translator->trans('cms.pages.preview.trigger'),
            'title' => $this->translator->trans('cms.pages.preview.title'),
            'description' => $this->translator->trans('cms.pages.preview.description'),
            'variant' => 'secondary',
        ], [
            new PageComponent('Shared:CopyField', [
                'id' => 'page-shared-preview-url',
                'label' => $this->translator->trans('cms.pages.preview.url_label'),
                'hint' => $this->translator->trans('cms.pages.preview.url_hint', [
                    '%expires%' => $formatter->format($sharedPreview->expiresAt),
                ]),
                'value' => $sharedPreview->url,
                'copyLabel' => $this->translator->trans('cms.pages.preview.copy'),
                'copiedMessage' => $this->translator->trans('cms.pages.preview.copied'),
                'failedMessage' => $this->translator->trans('cms.pages.preview.copy_failed'),
            ]),
            new PageComponent('Shared:ButtonLink', [
                'url' => $sharedPreview->url,
                'label' => $this->translator->trans('cms.pages.preview.open'),
            ]),
        ]);
    }

    /**
     * Where the site serves the page: the home page at its root, any other at
     * the slug it was published with.
     */
    private function publishedUrl(PageResponse $page): string
    {
        return $page->home
            ? $this->generateUrl('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->generateUrl('web_page', ['slug' => $page->publishedSlug], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Saving comes first, so it is what Enter in a field does. Publishing puts
     * what is saved on the site, so it waits while the form holds unsaved
     * changes; a published page offers it again only once it has changes to
     * publish.
     *
     * @return list<PageComponent>
     */
    private function formActions(PageResponse $page): array
    {
        $published = PageStatus::Published->value === $page->status;
        $publishable = $page->hasSomethingToPublish;
        $actions = [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.pages.edit.submit'),
                'variant' => $publishable ? 'secondary' : 'primary',
            ]),
        ];

        if ($publishable) {
            $actions[] = new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans($published ? 'cms.pages.edit.republish' : 'cms.pages.edit.publish'),
                'form' => self::PUBLISH_FORM,
                'waitsFor' => self::FORM,
                'waitingHint' => $this->translator->trans('cms.pages.edit.publish_unsaved'),
            ]);
        }

        return $actions;
    }

    /**
     * When the page was created and last changed, in the editor's language.
     */
    private function dates(PageResponse $page): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $this->translator->trans('cms.pages.edit.dates', [
            '%created%' => $formatter->format($page->createdAt),
            '%updated%' => $formatter->format($page->updatedAt),
        ]);
    }

    /**
     * The title of the page, a single line: it is also its title in search
     * results, cut around there.
     */
    private function titleField(PageResponse $page): PageComponent
    {
        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'page-title',
                'text' => $this->translator->trans('cms.pages.edit.title_label'),
            ]),
            new PageComponent('Shared:FormTextarea', [
                'name' => 'title',
                'id' => 'page-title',
                'value' => $page->title,
                'singleLine' => true,
                'recommendedMax' => SearchPreview::TITLE_LIMIT,
                'attributes' => [
                    'required' => true,
                    'minlength' => Title::MIN_LENGTH,
                    'maxlength' => Title::MAX_LENGTH,
                    'aria-describedby' => 'page-title-count',
                ],
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
                'csrfTokenId' => $route,
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
                'value' => $page->slug ?? '',
                'prefix' => '/',
                'suffix' => '.html',
                // It may be left blank until the page is published.
                'attributes' => [
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
        $baseUrl = rtrim($this->generateUrl('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL), '/');

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.pages.edit.seo.title'),
            'description' => $this->translator->trans('cms.pages.edit.seo.description'),
            'variant' => 'disclosure',
        ], [
            new PageComponent('Shared:SearchPreview', [
                'label' => $this->translator->trans('cms.pages.edit.seo.preview'),
                'title' => $page->title,
                'url' => $page->home ? $baseUrl.'/' : \sprintf('%s/%s.html', $baseUrl, $page->slug ?? ''),
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
