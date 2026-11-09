<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Settings\Application\SettingsFinder;
use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Infrastructure\Controller\SettingsState;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The settings, when they use a resource, as a row leading to where they are
 * edited.
 */
final readonly class SettingsUserRows
{
    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private SettingsRepository $settingsRepository,
        private SettingsState $settingsState,
    ) {}

    /**
     * @param list<string> $ids
     *
     * @return list<PageComponent>
     */
    public function __invoke(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        try {
            $settings = new SettingsFinder()($this->settingsRepository);
        } catch (SettingsNotFound) {
            return [];
        }

        return [
            new PageComponent('Shared:ResourceRow', [
                'title' => $this->translator->trans('cms.settings.title'),
                'url' => $this->urlGenerator->generate('cms_settings'),
                'meta' => \sprintf('%s · %s', $this->translator->trans('cms.relations.types.settings'), $settings->name),
                'status' => $this->settingsState->label($settings),
            ]),
        ];
    }
}
