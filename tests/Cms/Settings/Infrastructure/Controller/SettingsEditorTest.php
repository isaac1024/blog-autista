<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Infrastructure\Controller;

use Cms\Settings\Application\SettingsResponse;
use Cms\Settings\Domain\Menu;
use Cms\Settings\Domain\MenuItem;
use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SiteName;
use Cms\Settings\Infrastructure\Controller\SettingsEditor;
use Cms\Settings\Infrastructure\Controller\SettingsState;
use Cms\Shared\Infrastructure\Ui\LinkTargets;
use Cms\Tests\Settings\Domain\SettingsMother as Mother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the editor of the settings makes: what it offers, and the
 * fields it saves them with. Routes are generated as their names; the texts
 * are their translation keys, with what their placeholders are given.
 */
final class SettingsEditorTest extends TestCase
{
    /**
     * When the settings were created and last changed, in the language of
     * the editor.
     */
    public function testItTellsWhenTheSettingsWereCreatedAndChanged(): void
    {
        $editor = $this->edit(Mother::create(createdAt: '2026-10-01 09:00:00', updatedAt: '2026-11-09 18:30:00'));

        PageComponents::one($editor, 'Shared:PageTitle', ['subtitle' => 'cms.settings.dates(%created%=1 oct 2026, %updated%=9 nov 2026)']);
    }

    /**
     * Changes not on the site yet can be discarded, confirmed first and
     * submitted with a button of their own.
     */
    #[DataProvider('discardingByState')]
    public function testItOffersToDiscardOnlyChangesNotOnTheSite(Settings $settings, bool $offered): void
    {
        $editor = $this->edit($settings);

        $forms = PageComponents::find($editor, 'Shared:Form', ['action' => '/cms_settings_discard']);
        self::assertSame($offered, [] !== $forms);
        foreach ($forms as $form) {
            self::assertSame(['Shared:FormButton'], array_map(static fn (PageComponent $child): string => $child->name, $form->children));
        }
    }

    /**
     * @return iterable<string, array{Settings, bool}>
     */
    public static function discardingByState(): iterable
    {
        yield 'a draft' => [Mother::create(), false];
        yield 'published as they are on the site' => [Mother::published(), false];
        yield 'published with changes' => [Mother::published(Mother::version(name: 'Otro nombre'), Mother::version()), true];
    }

    /**
     * Publishing is offered while it would change the site, worded for
     * whether the settings are there already, and saving is the main action
     * otherwise.
     */
    #[DataProvider('publishingByState')]
    public function testItOffersToPublishOnlyWhatWouldChangeTheSite(Settings $settings, ?string $publishLabel, string $saveVariant): void
    {
        $editor = $this->edit($settings);

        $publish = PageComponents::find($editor, 'Shared:FormButton', ['form' => 'settings-publish-form', 'waitsFor' => 'settings-form']);
        self::assertSame(null === $publishLabel ? [] : [$publishLabel], array_map(static fn (PageComponent $button): mixed => $button->props['label'], $publish));
        PageComponents::one($editor, 'Shared:FormButton', ['label' => 'cms.settings.submit', 'variant' => $saveVariant]);
        // It publishes what is saved, so it is a form of its own, out of sight.
        PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_settings_publish', 'id' => 'settings-publish-form', 'hidden' => true]);
    }

    /**
     * @return iterable<string, array{Settings, ?string, string}>
     */
    public static function publishingByState(): iterable
    {
        yield 'a draft' => [Mother::create(), 'cms.settings.publish', 'secondary'];
        yield 'published as they are on the site' => [Mother::published(), null, 'primary'];
        yield 'published with changes' => [Mother::published(Mother::version(name: 'Otro nombre'), Mother::version()), 'cms.settings.republish', 'secondary'];
    }

    /**
     * Where the settings stand is told by their badge and by the bar of
     * their actions; changes not on the site yet are what matters most.
     */
    #[DataProvider('settingsWhereverTheyStand')]
    public function testItTellsWhereTheSettingsStand(Settings $settings, string $state): void
    {
        $editor = $this->edit($settings);

        PageComponents::one($editor, 'Shared:PageTitle', ['status' => \sprintf('cms.settings.status.%s.label', $state)]);
        PageComponents::one($editor, 'Shared:ActionBar', ['status' => \sprintf('cms.settings.status.%s.description', $state)]);
    }

    /**
     * @return iterable<string, array{Settings, string}>
     */
    public static function settingsWhereverTheyStand(): iterable
    {
        yield 'a draft' => [Mother::create(), 'draft'];
        yield 'published as they are on the site' => [Mother::published(), 'published'];
        yield 'published with changes' => [Mother::published(Mother::version(name: 'Otro nombre'), Mother::version()), 'changed'];
    }

    /**
     * The editor saves the settings with the fields their update reads, held
     * to what they accept: a name, a default image picked among the images,
     * and two menus of any content a text links, each as long as a menu can be.
     */
    public function testItSavesTheSettingsWithTheFieldsTheirUpdateReads(): void
    {
        $mainMenu = [Mother::item(Mother::PAGE, 'Sobre mí'), Mother::item(Mother::OTHER_PAGE)];
        $footerMenu = [Mother::item(Mother::THIRD_PAGE)];
        $editor = $this->edit(Mother::create(Mother::version(name: 'Blog autista', defaultImage: Mother::IMAGE, mainMenu: $mainMenu, footerMenu: $footerMenu)));

        $form = PageComponents::one($editor, 'Shared:Form', ['action' => '/cms_settings_update', 'id' => 'settings-form']);
        $name = PageComponents::one($form, 'Shared:FormInput', ['name' => 'name', 'value' => 'Blog autista']);
        self::assertSame(['required' => true, 'minlength' => SiteName::MIN_LENGTH, 'maxlength' => SiteName::MAX_LENGTH], $name->props['attributes']);
        PageComponents::one($form, 'Shared:ImageField', [
            'name' => 'default_image',
            'value' => Mother::IMAGE,
            'searchUrl' => '/cms_images_search',
            'lookupUrl' => '/cms_images_lookup',
        ]);
        foreach (['main_menu' => $mainMenu, 'footer_menu' => $footerMenu] as $menu => $items) {
            PageComponents::one($form, 'Shared:MenuField', [
                'name' => $menu,
                'items' => Menu::fromArray($items)->view(),
                'targets' => new LinkTargets(new RouteNameUrlGenerator())(null),
                'maxItems' => Menu::MAX_ITEMS,
                'labelMaxLength' => MenuItem::LABEL_MAX_LENGTH,
            ]);
        }
        PageComponents::one($form, 'Shared:Panel', ['description' => \sprintf('cms.settings.menus.description(%%max%%=%d)', Menu::MAX_ITEMS)]);
    }

    /**
     * Settings without a default image leave its field empty to pick one.
     */
    public function testItLeavesTheDefaultImageToPickWhileThereIsNone(): void
    {
        $editor = $this->edit(Mother::create());

        PageComponents::one($editor, 'Shared:ImageField', ['name' => 'default_image', 'value' => '']);
    }

    private function edit(Settings $settings): Page
    {
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $editor = new SettingsEditor(new RouteNameUrlGenerator(), $translator, new SettingsState($translator), new LinkTargets(new RouteNameUrlGenerator()))(SettingsResponse::fromSettings($settings));
        // Whatever a test asks, the editor gives every component what it takes.
        ComponentContracts::assertHonoured($editor);

        return $editor;
    }
}
