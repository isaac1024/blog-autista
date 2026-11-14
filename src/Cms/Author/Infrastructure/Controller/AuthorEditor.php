<?php

declare(strict_types=1);

namespace Cms\Author\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Domain\Author;
use Cms\Author\Domain\AuthorName;
use Cms\Author\Domain\AuthorStatus;
use Cms\Author\Domain\AvatarFile;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Twig\Components\SearchPreview;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the editor of an author: where it stands and what is done to it
 * apart from editing, its fields, its avatar and its bio, and the actions
 * that save and publish it, each offered only when the author allows it.
 *
 * Composing a page reaches every component it is made of, so its coupling
 * grows with the page and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class AuthorEditor
{
    private const string FORM = 'author-form';

    private const string PUBLISH_FORM = 'author-publish-form';

    /**
     * Quality of the WebP the browser encodes: 0.85, where the loss doesn't show.
     */
    private const float AVATAR_QUALITY = 0.85;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private TiptapRichText $tiptapRichText,
        private AuthorState $authorState,
        private AvatarUrl $avatarUrl,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(AuthorResponse $author, string $previewUrl, \DateTimeImmutable $previewExpiresAt): Page
    {
        return CmsLayout::page(\sprintf(
            '%s · %s · CMS',
            $author->name,
            $this->translator->trans('cms.authors.edit.title'),
        ))
            // What is being edited and where it stands, with what is done to it apart from editing.
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.authors.edit.title'),
                'subtitle' => $this->dates($author),
                'status' => $this->authorState->label($author),
            ], $this->authorActions($author, $previewUrl, $previewExpiresAt))
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_authors_update', ['id' => $author->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_authors_update',
                'id' => self::FORM,
                'enctype' => 'multipart/form-data',
            ], [
                $this->nameField($author),
                $this->slugField($author),
                $this->avatarField($author, $previewExpiresAt),
                new PageComponent('Shared:RichTextEditor', [
                    'name' => 'bio',
                    'id' => 'author-bio-editor',
                    'label' => $this->translator->trans('cms.authors.edit.bio_title'),
                    'placeholder' => $this->translator->trans('cms.authors.edit.bio_placeholder'),
                    'value' => json_encode($this->tiptapRichText->toTiptap($author->bio), \JSON_THROW_ON_ERROR),
                    'features' => Author::bioFeatures()->toArray(),
                    'imageSearchUrl' => $this->urlGenerator->generate('cms_images_search'),
                    'imageLookupUrl' => $this->urlGenerator->generate('cms_images_lookup'),
                    'pageSearchUrl' => $this->urlGenerator->generate('cms_pages_search'),
                    'pageLookupUrl' => $this->urlGenerator->generate('cms_pages_lookup'),
                ]),
                $this->searchEngineSection($author),
                new PageComponent('Shared:ActionBar', [
                    'status' => $this->authorState->description($author),
                ], $this->formActions($author)),
            ])
            // Publishing takes what is saved, not what the edit form holds, so
            // it is a form of its own, which its button in the bar submits.
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_authors_publish', ['id' => $author->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_authors_publish',
                'id' => self::PUBLISH_FORM,
                'hidden' => true,
            ]);
    }

    /**
     * What is done to the author apart from editing it, offered only when the
     * author allows it: previewing what is saved, through a link that expires
     * and can be sent to anyone, seeing it on the site, seeing what its bio
     * uses, and, each confirmed first, discarding its unpublished changes,
     * taking it off the site, or deleting it.
     *
     * @return list<PageComponent>
     */
    private function authorActions(AuthorResponse $author, string $previewUrl, \DateTimeImmutable $previewExpiresAt): array
    {
        $actions = [$this->previewModal($previewUrl, $previewExpiresAt)];

        // The author as the site serves it, at the address it has there,
        // which may differ from the one being edited.
        if (null !== $author->publishedSlug && AuthorStatus::Published->value === $author->status) {
            $actions[] = new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('web_author', ['slug' => $author->publishedSlug], UrlGeneratorInterface::ABSOLUTE_URL),
                'label' => $this->translator->trans('cms.authors.edit.view_on_site'),
            ]);
        }

        if ($author->changed) {
            $actions[] = $this->confirmModal($author, 'discard', 'cms_authors_discard', 'secondary');
        }

        if ($author->canBeUnpublished) {
            $actions[] = $this->confirmModal($author, 'unpublish', 'cms_authors_unpublish', 'secondary');
        }

        if ($author->canBeDeleted) {
            $actions[] = $this->confirmModal($author, 'delete', 'cms_authors_delete', 'danger');
        }

        return $actions;
    }

    /**
     * The version last saved, as the site would serve it once published,
     * behind a signed link that shows it to anyone who has it, without
     * signing in, until it expires: the editor opens it, or copies it to send.
     */
    private function previewModal(string $previewUrl, \DateTimeImmutable $expiresAt): PageComponent
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT);

        return new PageComponent('Shared:Modal', [
            'id' => 'preview',
            'trigger' => $this->translator->trans('cms.authors.preview.trigger'),
            'title' => $this->translator->trans('cms.authors.preview.title'),
            'description' => $this->translator->trans('cms.authors.preview.description'),
            'variant' => 'secondary',
        ], [
            new PageComponent('Shared:CopyField', [
                'id' => 'author-shared-preview-url',
                'label' => $this->translator->trans('cms.authors.preview.url_label'),
                'hint' => $this->translator->trans('cms.authors.preview.url_hint', [
                    '%expires%' => $formatter->format($expiresAt),
                ]),
                'value' => $previewUrl,
                'copyLabel' => $this->translator->trans('cms.authors.preview.copy'),
                'copiedMessage' => $this->translator->trans('cms.authors.preview.copied'),
                'failedMessage' => $this->translator->trans('cms.authors.preview.copy_failed'),
            ]),
            new PageComponent('Shared:ButtonLink', [
                'url' => $previewUrl,
                'label' => $this->translator->trans('cms.authors.preview.open'),
            ]),
        ]);
    }

    /**
     * Saving comes first, so it is what Enter in a field does. Publishing puts
     * what is saved on the site, so it waits while the form holds unsaved
     * changes; a published author offers it again only once it has changes
     * to publish.
     *
     * @return list<PageComponent>
     */
    private function formActions(AuthorResponse $author): array
    {
        $publishable = $author->hasSomethingToPublish;
        $actions = [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.authors.edit.submit'),
                'variant' => $publishable ? 'secondary' : 'primary',
            ]),
        ];

        if ($publishable) {
            $actions[] = new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans(AuthorStatus::Published->value === $author->status ? 'cms.authors.edit.republish' : 'cms.authors.edit.publish'),
                'form' => self::PUBLISH_FORM,
                'waitsFor' => self::FORM,
                'waitingHint' => $this->translator->trans('cms.authors.edit.publish_unsaved'),
            ]);
        }

        return $actions;
    }

    /**
     * When the author was created and last changed, in the editor's language.
     */
    private function dates(AuthorResponse $author): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return $this->translator->trans('cms.authors.edit.dates', [
            '%created%' => $formatter->format($author->createdAt),
            '%updated%' => $formatter->format($author->updatedAt),
        ]);
    }

    /**
     * The name the author signs with, a single line: it is also the title of
     * its page in search results.
     */
    private function nameField(AuthorResponse $author): PageComponent
    {
        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'author-name',
                'text' => $this->translator->trans('cms.authors.name_label'),
            ]),
            new PageComponent('Shared:FormTextarea', [
                'name' => 'name',
                'id' => 'author-name',
                'value' => $author->name,
                'singleLine' => true,
                'recommendedMax' => SearchPreview::TITLE_LIMIT,
                'attributes' => [
                    'required' => true,
                    'minlength' => AuthorName::MIN_LENGTH,
                    'maxlength' => AuthorName::MAX_LENGTH,
                    'aria-describedby' => 'author-name-count',
                ],
            ]),
        ]);
    }

    private function slugField(AuthorResponse $author): PageComponent
    {
        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'author-slug',
                'text' => $this->translator->trans('cms.authors.edit.slug_label'),
            ]),
            new PageComponent('Shared:FormHint', [
                'id' => 'author-slug-hint',
                'text' => $this->translator->trans('cms.authors.edit.slug_hint'),
            ]),
            new PageComponent('Shared:FormInput', [
                'name' => 'slug',
                'id' => 'author-slug',
                'value' => $author->slug ?? '',
                'prefix' => '/autor/',
                'suffix' => '.html',
                // It may be left blank until the author is published.
                'attributes' => [
                    'minlength' => Slug::MIN_LENGTH,
                    'maxlength' => Slug::MAX_LENGTH,
                    'pattern' => Slug::SHAPE,
                    'autocapitalize' => 'none',
                    'spellcheck' => 'false',
                    'aria-describedby' => 'author-slug-hint',
                ],
            ]),
        ]);
    }

    /**
     * The avatar being edited, if any, and a way to replace it, cropped
     * square in the browser. The form goes without one until it is chosen.
     */
    private function avatarField(AuthorResponse $author, \DateTimeImmutable $expiresAt): PageComponent
    {
        $children = [];

        if (null !== $author->avatar) {
            $children[] = new PageComponent('Shared:Avatar', [
                'src' => ($this->avatarUrl)($author->id, $author->avatar, $expiresAt),
                'srcset' => '',
                'size' => AvatarFile::SIZE,
                'alt' => $this->translator->trans('cms.authors.edit.avatar_current', ['%name%' => $author->name]),
            ]);
        }

        $children[] = new PageComponent('Shared:ImageCropper', [
            'name' => 'avatar',
            'id' => 'author-avatar',
            'label' => $this->translator->trans(null === $author->avatar ? 'cms.authors.edit.avatar_label' : 'cms.authors.edit.avatar_replace'),
            'width' => AvatarFile::SIZE,
            'height' => AvatarFile::SIZE,
            'quality' => self::AVATAR_QUALITY,
            'required' => false,
        ]);

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.authors.edit.avatar_title'),
            'description' => $this->translator->trans('cms.authors.edit.avatar_description'),
        ], $children);
    }

    /**
     * A modal that confirms an action on the author, with its texts under
     * cms.authors.{action}.
     */
    private function confirmModal(AuthorResponse $author, string $action, string $route, string $variant): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => $action.'-author',
            'trigger' => $this->translator->trans(\sprintf('cms.authors.%s.trigger', $action)),
            'title' => $this->translator->trans(\sprintf('cms.authors.%s.title', $action)),
            'description' => $this->translator->trans(\sprintf('cms.authors.%s.description', $action), ['%name%' => $author->name]),
            'variant' => $variant,
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate($route, ['id' => $author->id]),
                'method' => 'post',
                'csrfTokenId' => $route,
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans(\sprintf('cms.authors.%s.submit', $action)),
                    'variant' => 'danger',
                ]),
            ]),
        ]);
    }

    /**
     * How the page of the author shows in search results, previewed above
     * the settings that shape it, so every change is seen where it lands.
     */
    private function searchEngineSection(AuthorResponse $author): PageComponent
    {
        $address = str_replace('slug-placeholder', '%slug%', $this->urlGenerator->generate('web_author', ['slug' => 'slug-placeholder'], UrlGeneratorInterface::ABSOLUTE_URL));

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.authors.seo.title'),
            'description' => $this->translator->trans('cms.authors.seo.description'),
            'variant' => 'disclosure',
        ], [
            new PageComponent('Shared:SearchPreview', [
                'label' => $this->translator->trans('cms.authors.seo.preview'),
                'title' => $author->name,
                'url' => str_replace('%slug%', $author->slug ?? '', $address),
                'address' => $address,
                'description' => $author->metaDescription,
                'emptyDescription' => $this->translator->trans('cms.authors.seo.preview_empty'),
                'sources' => ['title' => 'author-name', 'description' => 'author-meta-description', 'slug' => 'author-slug'],
            ]),
            new PageComponent('Shared:FormField', [], [
                new PageComponent('Shared:FormLabel', [
                    'for' => 'author-meta-description',
                    'text' => $this->translator->trans('cms.authors.seo.meta_description_label'),
                ]),
                new PageComponent('Shared:FormHint', [
                    'id' => 'author-meta-description-hint',
                    'text' => $this->translator->trans('cms.authors.edit.meta_description_hint'),
                ]),
                new PageComponent('Shared:FormTextarea', [
                    'name' => 'meta_description',
                    'id' => 'author-meta-description',
                    'value' => $author->metaDescription,
                    'recommendedMin' => Seo::DESCRIPTION_RECOMMENDED_MIN_LENGTH,
                    'recommendedMax' => Seo::DESCRIPTION_RECOMMENDED_MAX_LENGTH,
                    'attributes' => [
                        'maxlength' => Seo::DESCRIPTION_MAX_LENGTH,
                        'aria-describedby' => 'author-meta-description-hint author-meta-description-count',
                    ],
                ]),
            ]),
            new PageComponent('Shared:FormRadioGroup', [
                'name' => 'robots',
                'id' => 'author-robots',
                'legend' => $this->translator->trans('cms.authors.seo.robots_legend'),
                'value' => $author->robots,
                'options' => array_map(fn (Robots $robots): array => [
                    'value' => $robots->value,
                    'label' => $this->translator->trans('cms.authors.seo.robots.'.$robots->value.'.label'),
                    'hint' => $this->translator->trans('cms.authors.seo.robots.'.$robots->value.'.hint'),
                ], Robots::cases()),
            ]),
        ]);
    }
}
