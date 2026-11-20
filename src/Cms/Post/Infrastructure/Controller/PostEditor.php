<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Post\Application\PostResponse;
use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostStatus;
use Cms\Post\Domain\PostTitle;
use Cms\Shared\Domain\Robots;
use Cms\Shared\Domain\Seo;
use Cms\Shared\Domain\Slug;
use Cms\Shared\Infrastructure\Publication\LastAddressPanel;
use Cms\Shared\Infrastructure\Ui\AuthorState;
use Cms\Shared\Infrastructure\Ui\LinkTargets;
use Cms\Shared\Infrastructure\Ui\PostState;
use Cms\Shared\Infrastructure\Ui\TermState;
use Cms\Taxonomy\Application\TermResponse;
use Shared\Domain\Clock;
use Shared\Domain\SiteTimeZone;
use Shared\Infrastructure\RichText\TiptapRichText;
use Shared\Infrastructure\Twig\Components\SearchPreview;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the editor of a post: where it stands and what is done to it
 * apart from editing, its fields, where it is filed and who signs it, the
 * image it opens with, its content, and the actions that save and publish it, each offered only when
 * the post allows it.
 *
 * Composing a page reaches every component it is made of, and the sections,
 * tags and authors it is filed under, so its coupling grows with the page
 * and says nothing about its design.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
final readonly class PostEditor
{
    private const string FORM = 'post-form';

    private const string PUBLISH_FORM = 'post-publish-form';

    /**
     * It names the state of the post and of the sections, tags and authors
     * it may be filed under, each as its own module does.
     *
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private TiptapRichText $tiptapRichText,
        private PostState $postState,
        private PostUrl $postUrl,
        private TermState $termState,
        private AuthorState $authorState,
        private LastAddressPanel $lastAddressPanel,
        private LinkTargets $linkTargets,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @param list<TermResponse>   $sections every section, to pick one from
     * @param list<AuthorResponse> $authors  every author, to pick one from
     * @param list<TermResponse>   $tags     the tags of the post, in order
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __invoke(
        PostResponse $post,
        array $sections,
        array $authors,
        array $tags,
        string $previewUrl,
        \DateTimeImmutable $previewExpiresAt,
    ): Page {
        $address = $this->addressPrefix($post, $sections);

        return $this->lastAddressPanel->onPost(CmsLayout::page(\sprintf(
            '%s · %s · CMS',
            $post->title,
            $this->translator->trans('cms.posts.edit.title'),
        ))
            // What is being edited and where it stands, with what is done to it apart from editing.
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.posts.edit.title'),
                'subtitle' => $this->dates($post),
                'status' => $this->postState->label($post),
            ], $this->postActions($post, $previewUrl, $previewExpiresAt)), $post->id)
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_posts_update', ['id' => $post->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_posts_update',
                'id' => self::FORM,
            ], [
                $this->titleField($post),
                $this->slugField($post, $address),
                $this->classificationSection($post, $sections, $authors, $tags),
                // The image the post opens with, also the one it is shared with.
                new PageComponent('Shared:ImageField', [
                    'name' => 'opening_image',
                    'id' => 'post-opening-image',
                    'label' => $this->translator->trans('cms.posts.edit.opening_image_label'),
                    'hint' => $this->translator->trans('cms.posts.edit.opening_image_hint'),
                    'value' => $post->openingImageId ?? '',
                    'searchUrl' => $this->urlGenerator->generate('cms_images_search'),
                    'lookupUrl' => $this->urlGenerator->generate('cms_images_lookup'),
                    'offSite' => $this->translator->trans('cms.posts.edit.opening_image_off_site'),
                ]),
                new PageComponent('Shared:RichTextEditor', [
                    'name' => 'content',
                    'id' => 'post-content-editor',
                    'label' => $this->translator->trans('cms.posts.edit.content_title'),
                    'placeholder' => $this->translator->trans('cms.posts.edit.content_placeholder'),
                    'value' => json_encode($this->tiptapRichText->toTiptap($post->content), \JSON_THROW_ON_ERROR),
                    'features' => Post::contentFeatures()->toArray(),
                    'imageSearchUrl' => $this->urlGenerator->generate('cms_images_search'),
                    'imageLookupUrl' => $this->urlGenerator->generate('cms_images_lookup'),
                    // A page or a post doesn't link itself, so the search leaves it out.
                    'linkTargets' => ($this->linkTargets)($post->id),
                ]),
                $this->searchEngineSection($post, $address),
                new PageComponent('Shared:ActionBar', [
                    'status' => $this->postState->description($post),
                ], $this->formActions($post)),
            ])
            // Publishing takes what is saved, not what the edit form holds, so
            // it is a form of its own, which its button in the bar submits.
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_posts_publish', ['id' => $post->id]),
                'method' => 'post',
                'csrfTokenId' => 'cms_posts_publish',
                'id' => self::PUBLISH_FORM,
                'hidden' => true,
            ]);
    }

    /**
     * What is done to the post apart from editing it, offered only when the
     * post allows it: previewing what is saved, through a link that expires
     * and can be sent to anyone, seeing it on the site, seeing what leads to
     * it, taking it off the site, and, each confirmed first, discarding its
     * unpublished changes or deleting it.
     *
     * @return list<PageComponent>
     */
    private function postActions(PostResponse $post, string $previewUrl, \DateTimeImmutable $previewExpiresAt): array
    {
        $actions = [$this->previewModal($previewUrl, $previewExpiresAt)];
        $url = $this->postUrl->onSite($post, UrlGeneratorInterface::ABSOLUTE_URL);

        // The post as the site serves it, at the address it has there, which
        // may differ from the one being edited.
        if (null !== $url) {
            $actions[] = new PageComponent('Shared:ButtonLink', [
                'url' => $url,
                'label' => $this->translator->trans('cms.posts.edit.view_on_site'),
            ]);
        }

        // What leads to the post, on a page of its own.
        $actions[] = new PageComponent('Shared:ButtonLink', [
            'url' => $this->urlGenerator->generate('cms_relations', ['type' => 'posts', 'id' => $post->id]),
            'label' => $this->translator->trans('cms.posts.edit.relations'),
        ]);

        if ($post->changed) {
            $actions[] = $this->confirmModal($post, 'discard', 'cms_posts_discard', 'secondary');
        }

        if ($post->canBeUnpublished) {
            // Taking it off the site asks what becomes of its addresses, on a
            // page of its own.
            $actions[] = new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('cms_posts_unpublish_form', ['id' => $post->id]),
                'label' => $this->translator->trans('cms.posts.unpublish.trigger'),
            ]);
        }

        if ($post->canBeDeleted) {
            $actions[] = $this->confirmModal($post, 'delete', 'cms_posts_delete', 'danger');
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
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT, SiteTimeZone::NAME);

        return new PageComponent('Shared:Modal', [
            'id' => 'preview',
            'trigger' => $this->translator->trans('cms.posts.preview.trigger'),
            'title' => $this->translator->trans('cms.posts.preview.title'),
            'description' => $this->translator->trans('cms.posts.preview.description'),
            'variant' => 'secondary',
        ], [
            new PageComponent('Shared:CopyField', [
                'id' => 'post-shared-preview-url',
                'label' => $this->translator->trans('cms.posts.preview.url_label'),
                'hint' => $this->translator->trans('cms.posts.preview.url_hint', [
                    '%expires%' => $formatter->format($expiresAt),
                ]),
                'value' => $previewUrl,
                'copyLabel' => $this->translator->trans('cms.posts.preview.copy'),
                'copiedMessage' => $this->translator->trans('cms.posts.preview.copied'),
                'failedMessage' => $this->translator->trans('cms.posts.preview.copy_failed'),
            ]),
            new PageComponent('Shared:ButtonLink', [
                'url' => $previewUrl,
                'label' => $this->translator->trans('cms.posts.preview.open'),
            ]),
        ]);
    }

    /**
     * Saving comes first, so it is what Enter in a field does. Publishing puts
     * what is saved on the site, so it waits while the form holds unsaved
     * changes; a published post offers it again only once it has changes to
     * publish.
     *
     * @return list<PageComponent>
     */
    private function formActions(PostResponse $post): array
    {
        $publishable = $post->hasSomethingToPublish;
        $actions = [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.posts.edit.submit'),
                'variant' => $publishable ? 'secondary' : 'primary',
            ]),
        ];

        if ($publishable) {
            $actions[] = new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans(PostStatus::Published->value === $post->status ? 'cms.posts.edit.republish' : 'cms.posts.edit.publish'),
                'form' => self::PUBLISH_FORM,
                'waitsFor' => self::FORM,
                'waitingHint' => $this->translator->trans('cms.posts.edit.publish_unsaved'),
            ]);
        }

        return $actions;
    }

    /**
     * When the post was created and last changed, in the editor's language.
     */
    private function dates(PostResponse $post): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, SiteTimeZone::NAME);

        return $this->translator->trans('cms.posts.edit.dates', [
            '%created%' => $formatter->format($post->createdAt),
            '%updated%' => $formatter->format($post->updatedAt),
        ]);
    }

    /**
     * The title of the post, a single line: it is also its title in search
     * results.
     */
    private function titleField(PostResponse $post): PageComponent
    {
        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'post-title',
                'text' => $this->translator->trans('cms.posts.title_label'),
            ]),
            new PageComponent('Shared:FormTextarea', [
                'name' => 'title',
                'id' => 'post-title',
                'value' => $post->title,
                'singleLine' => true,
                'recommendedMax' => SearchPreview::TITLE_LIMIT,
                'attributes' => [
                    'required' => true,
                    'minlength' => PostTitle::MIN_LENGTH,
                    'maxlength' => PostTitle::MAX_LENGTH,
                    'aria-describedby' => 'post-title-count',
                ],
            ]),
        ]);
    }

    private function slugField(PostResponse $post, string $address): PageComponent
    {
        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => 'post-slug',
                'text' => $this->translator->trans('cms.posts.edit.slug_label'),
            ]),
            new PageComponent('Shared:FormHint', [
                'id' => 'post-slug-hint',
                'text' => $this->translator->trans('cms.posts.edit.slug_hint'),
            ]),
            new PageComponent('Shared:FormInput', [
                'name' => 'slug',
                'id' => 'post-slug',
                'value' => $post->slug ?? '',
                'prefix' => $address,
                'suffix' => '.html',
                // It may be left blank until the post is published.
                'attributes' => [
                    'minlength' => Slug::MIN_LENGTH,
                    'maxlength' => Slug::MAX_LENGTH,
                    'pattern' => Slug::SHAPE,
                    'autocapitalize' => 'none',
                    'spellcheck' => 'false',
                    'aria-describedby' => 'post-slug-hint',
                ],
            ]),
        ]);
    }

    /**
     * Where the post is filed and who signs it: a section and an author, each
     * picked from all of them, both needed on the site to publish, and the
     * tags, added by searching them, which the site only shows while they
     * are on it.
     *
     * @param list<TermResponse>   $sections
     * @param list<AuthorResponse> $authors
     * @param list<TermResponse>   $tags
     */
    private function classificationSection(PostResponse $post, array $sections, array $authors, array $tags): PageComponent
    {
        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.posts.classification.title'),
            'description' => $this->translator->trans('cms.posts.classification.description'),
        ], [
            $this->selectField('section', $post->sectionId ?? '', array_map(fn (TermResponse $section): array => [
                'value' => $section->id,
                'label' => $this->optionLabel($section->name, $section->isPublished(), $this->termState->label($section)),
            ], $sections)),
            $this->selectField('author', $post->authorId ?? '', array_map(fn (AuthorResponse $author): array => [
                'value' => $author->id,
                'label' => $this->optionLabel($author->name, $author->isPublished(), $this->authorState->label($author)),
            ], $authors)),
            new PageComponent('Shared:TermsField', [
                'name' => 'tags',
                'id' => 'post-tags',
                'label' => $this->translator->trans('cms.posts.classification.tags_label'),
                'hint' => $this->translator->trans('cms.posts.classification.tags_hint'),
                'items' => array_map(fn (TermResponse $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'status' => $this->termState->label($tag),
                    'note' => $tag->isPublished() ? '' : $this->translator->trans('cms.posts.classification.tag_off_site'),
                ], $tags),
                'searchUrl' => $this->urlGenerator->generate('cms_terms_search', ['taxonomy' => 'tags']),
                'offSite' => $this->translator->trans('cms.posts.classification.tag_off_site'),
            ]),
        ]);
    }

    /**
     * A section or an author to pick, by name, saying where it stands unless
     * it is on the site.
     */
    private function optionLabel(string $name, bool $onSite, string $status): string
    {
        return $onSite ? $name : \sprintf('%s · %s', $name, $status);
    }

    /**
     * A list to pick the section or the author from, with its texts under
     * cms.posts.classification.{field}.
     *
     * @param list<array{value: string, label: string}> $options
     */
    private function selectField(string $field, string $value, array $options): PageComponent
    {
        $id = 'post-'.$field;

        return new PageComponent('Shared:FormField', [], [
            new PageComponent('Shared:FormLabel', [
                'for' => $id,
                'text' => $this->translator->trans(\sprintf('cms.posts.classification.%s_label', $field)),
            ]),
            new PageComponent('Shared:FormHint', [
                'id' => $id.'-hint',
                'text' => $this->translator->trans(\sprintf('cms.posts.classification.%s_hint', $field)),
            ]),
            new PageComponent('Shared:FormSelect', [
                'name' => $field,
                'id' => $id,
                'value' => $value,
                'none' => $this->translator->trans(\sprintf('cms.posts.classification.%s_none', $field)),
                'options' => $options,
                'attributes' => ['aria-describedby' => $id.'-hint'],
            ]),
        ]);
    }

    /**
     * Where the site serves the post but its slug: under the slug of the
     * section chosen, as it has it on the site, on the day it was first
     * published, or today, which it would take if published now. The
     * section only says where it is filed: changing it moves the post.
     *
     * @param list<TermResponse> $sections
     */
    private function addressPrefix(PostResponse $post, array $sections): string
    {
        $section = null;

        foreach ($sections as $candidate) {
            if ($candidate->id === $post->sectionId) {
                $section = $candidate->publishedSlug ?? $candidate->slug;
            }
        }

        $day = $post->publishedDay
            ?? ($post->firstPublishedAt ?? Clock::now())->setTimezone(SiteTimeZone::zone())->format('Y-m-d');

        return \sprintf(
            '/%s/%s/',
            $section ?? $this->translator->trans('cms.posts.edit.no_section'),
            str_replace('-', '/', $day),
        );
    }

    /**
     * A modal that confirms an action on the post, with its texts under
     * cms.posts.{action}.
     */
    private function confirmModal(PostResponse $post, string $action, string $route, string $variant): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => $action.'-post',
            'trigger' => $this->translator->trans(\sprintf('cms.posts.%s.trigger', $action)),
            'title' => $this->translator->trans(\sprintf('cms.posts.%s.title', $action)),
            'description' => $this->translator->trans(\sprintf('cms.posts.%s.description', $action), ['%title%' => $post->title]),
            'variant' => $variant,
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate($route, ['id' => $post->id]),
                'method' => 'post',
                'csrfTokenId' => $route,
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans(\sprintf('cms.posts.%s.submit', $action)),
                    'variant' => 'danger',
                ]),
            ]),
        ]);
    }

    /**
     * How the post shows in search results, previewed above the settings that
     * shape it, so every change is seen where it lands.
     */
    private function searchEngineSection(PostResponse $post, string $address): PageComponent
    {
        $origin = rtrim($this->urlGenerator->generate('web_home', referenceType: UrlGeneratorInterface::ABSOLUTE_URL), '/');
        $template = $origin.$address.'%slug%.html';

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.posts.seo.title'),
            'description' => $this->translator->trans('cms.posts.seo.description'),
            'variant' => 'disclosure',
        ], [
            new PageComponent('Shared:SearchPreview', [
                'label' => $this->translator->trans('cms.posts.seo.preview'),
                'title' => $post->title,
                'url' => str_replace('%slug%', $post->slug ?? '', $template),
                'address' => $template,
                'description' => $post->metaDescription,
                'emptyDescription' => $this->translator->trans('cms.posts.seo.preview_empty'),
                'sources' => ['title' => 'post-title', 'description' => 'post-meta-description', 'slug' => 'post-slug'],
            ]),
            new PageComponent('Shared:FormField', [], [
                new PageComponent('Shared:FormLabel', [
                    'for' => 'post-meta-description',
                    'text' => $this->translator->trans('cms.posts.seo.meta_description_label'),
                ]),
                new PageComponent('Shared:FormHint', [
                    'id' => 'post-meta-description-hint',
                    'text' => $this->translator->trans('cms.posts.edit.meta_description_hint'),
                ]),
                new PageComponent('Shared:FormTextarea', [
                    'name' => 'meta_description',
                    'id' => 'post-meta-description',
                    'value' => $post->metaDescription,
                    'recommendedMin' => Seo::DESCRIPTION_RECOMMENDED_MIN_LENGTH,
                    'recommendedMax' => Seo::DESCRIPTION_RECOMMENDED_MAX_LENGTH,
                    'attributes' => [
                        'maxlength' => Seo::DESCRIPTION_MAX_LENGTH,
                        'aria-describedby' => 'post-meta-description-hint post-meta-description-count',
                    ],
                ]),
            ]),
            new PageComponent('Shared:FormRadioGroup', [
                'name' => 'robots',
                'id' => 'post-robots',
                'legend' => $this->translator->trans('cms.posts.seo.robots_legend'),
                'value' => $post->robots,
                'options' => array_map(fn (Robots $robots): array => [
                    'value' => $robots->value,
                    'label' => $this->translator->trans('cms.posts.seo.robots.'.$robots->value.'.label'),
                    'hint' => $this->translator->trans('cms.posts.seo.robots.'.$robots->value.'.hint'),
                ], Robots::cases()),
            ]),
        ]);
    }
}
