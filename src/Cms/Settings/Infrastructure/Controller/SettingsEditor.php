<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Controller;

use Cms\Settings\Application\SettingsResponse;
use Cms\Settings\Domain\Menu;
use Cms\Settings\Domain\MenuItem;
use Cms\Settings\Domain\SiteName;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Composes the editor of the settings of the site: the name of the blog and
 * its default image, then its main and footer menus, with the actions that
 * save, publish and discard them, each offered only when they allow it.
 */
final readonly class SettingsEditor
{
    private const string FORM = 'settings-form';

    private const string PUBLISH_FORM = 'settings-publish-form';

    private const string DISCARD_CHANGES_MODAL = 'discard-changes';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private SettingsState $settingsState,
    ) {}

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    public function __invoke(SettingsResponse $settings): Page
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);

        return CmsLayout::page(\sprintf('%s · CMS', $this->translator->trans('cms.settings.title')))
            ->withMain('Shared:PageTitle', [
                'title' => $this->translator->trans('cms.settings.title'),
                'subtitle' => $this->translator->trans('cms.settings.dates', [
                    '%created%' => $formatter->format($settings->createdAt),
                    '%updated%' => $formatter->format($settings->updatedAt),
                ]),
                'status' => $this->settingsState->label($settings),
            ], $settings->changed ? [$this->discardModal()] : [])
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_settings_update'),
                'method' => 'post',
                'csrfTokenId' => 'cms_settings_update',
                'id' => self::FORM,
            ], [
                new PageComponent('Shared:Panel', [
                    'title' => $this->translator->trans('cms.settings.blog.title'),
                    'description' => $this->translator->trans('cms.settings.blog.description'),
                ], [
                    new PageComponent('Shared:FormField', [], [
                        new PageComponent('Shared:FormLabel', [
                            'for' => 'settings-name',
                            'text' => $this->translator->trans('cms.settings.blog.name_label'),
                        ]),
                        new PageComponent('Shared:FormInput', [
                            'name' => 'name',
                            'id' => 'settings-name',
                            'value' => $settings->name,
                            'attributes' => [
                                'required' => true,
                                'minlength' => SiteName::MIN_LENGTH,
                                'maxlength' => SiteName::MAX_LENGTH,
                            ],
                        ]),
                    ]),
                    new PageComponent('Shared:ImageField', [
                        'name' => 'default_image',
                        'id' => 'settings-default-image',
                        'label' => $this->translator->trans('cms.settings.blog.image_label'),
                        'hint' => $this->translator->trans('cms.settings.blog.image_hint'),
                        'value' => $settings->defaultImage ?? '',
                        'searchUrl' => $this->urlGenerator->generate('cms_images_search'),
                        'lookupUrl' => $this->urlGenerator->generate('cms_images_lookup'),
                        'offSite' => $this->translator->trans('ui.image_field.off_site'),
                    ]),
                ]),
                new PageComponent('Shared:Panel', [
                    'title' => $this->translator->trans('cms.settings.menus.title'),
                    'description' => $this->translator->trans('cms.settings.menus.description', ['%max%' => Menu::MAX_ITEMS]),
                ], [
                    $this->menuField('main_menu', 'main', $settings->mainMenu),
                    $this->menuField('footer_menu', 'footer', $settings->footerMenu),
                ]),
                new PageComponent('Shared:ActionBar', [
                    'status' => $this->settingsState->description($settings),
                ], $this->formActions($settings)),
            ])
            // Publishing takes what is saved, not what the edit form holds, so
            // it is a form of its own, which its button in the bar submits.
            ->withMain('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_settings_publish'),
                'method' => 'post',
                'csrfTokenId' => 'cms_settings_publish',
                'id' => self::PUBLISH_FORM,
                'hidden' => true,
            ]);
    }

    /**
     * A menu field, with its texts under cms.settings.menus.{menu}.
     *
     * @param list<array{page_id: string, label: ?string}> $items
     */
    private function menuField(string $name, string $menu, array $items): PageComponent
    {
        return new PageComponent('Shared:MenuField', [
            'name' => $name,
            'id' => \sprintf('settings-%s-menu', $menu),
            'label' => $this->translator->trans(\sprintf('cms.settings.menus.%s.label', $menu)),
            'hint' => $this->translator->trans(\sprintf('cms.settings.menus.%s.hint', $menu)),
            'items' => $items,
            'searchUrl' => $this->urlGenerator->generate('cms_pages_search'),
            'lookupUrl' => $this->urlGenerator->generate('cms_pages_lookup'),
            'maxItems' => Menu::MAX_ITEMS,
            'labelMaxLength' => MenuItem::LABEL_MAX_LENGTH,
        ]);
    }

    /**
     * Saving comes first, so it is what Enter in a field does. Publishing puts
     * what is saved on the site, so it waits while the form holds unsaved
     * changes; published settings offer it again only once they have changes
     * to publish.
     *
     * @return list<PageComponent>
     */
    private function formActions(SettingsResponse $settings): array
    {
        $publishable = $settings->hasSomethingToPublish;
        $actions = [
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.settings.submit'),
                'variant' => $publishable ? 'secondary' : 'primary',
            ]),
        ];

        if ($publishable) {
            $actions[] = new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('published' === $settings->status ? 'cms.settings.republish' : 'cms.settings.publish'),
                'form' => self::PUBLISH_FORM,
                'waitsFor' => self::FORM,
                'waitingHint' => $this->translator->trans('cms.settings.publish_unsaved'),
            ]);
        }

        return $actions;
    }

    private function discardModal(): PageComponent
    {
        return new PageComponent('Shared:Modal', [
            'id' => self::DISCARD_CHANGES_MODAL,
            'trigger' => $this->translator->trans('cms.settings.discard.trigger'),
            'title' => $this->translator->trans('cms.settings.discard.title'),
            'description' => $this->translator->trans('cms.settings.discard.description'),
            'variant' => 'secondary',
        ], [
            new PageComponent('Shared:Form', [
                'action' => $this->urlGenerator->generate('cms_settings_discard'),
                'method' => 'post',
                'csrfTokenId' => 'cms_settings_discard',
            ], [
                new PageComponent('Shared:FormButton', [
                    'label' => $this->translator->trans('cms.settings.discard.submit'),
                    'variant' => 'danger',
                ]),
            ]),
        ]);
    }
}
