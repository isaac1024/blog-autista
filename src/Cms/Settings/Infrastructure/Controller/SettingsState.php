<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Controller;

use Cms\Settings\Application\SettingsResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where the settings stand for the site, in the editor's language: a draft,
 * published, or published with changes.
 */
final readonly class SettingsState
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * Short name of the state, for a badge.
     */
    public function label(SettingsResponse $settings): string
    {
        return $this->translator->trans(\sprintf('cms.settings.status.%s.label', $this->key($settings)));
    }

    /**
     * What the state means for the site, next to the actions that change it.
     */
    public function description(SettingsResponse $settings): string
    {
        return $this->translator->trans(\sprintf('cms.settings.status.%s.description', $this->key($settings)));
    }

    private function key(SettingsResponse $settings): string
    {
        return $settings->changed ? 'changed' : $settings->status;
    }
}
