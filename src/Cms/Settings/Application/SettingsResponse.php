<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\Settings;

/**
 * The settings as the CMS shows them.
 *
 * It carries everything the editor shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class SettingsResponse
{
    /**
     * @param ?string                                               $defaultImage          id of the default image; null for none
     * @param list<array{type: string, id: string, label: ?string}> $mainMenu
     * @param list<array{type: string, id: string, label: ?string}> $footerMenu
     * @param string                                                $status                one of the SettingsStatus values
     * @param bool                                                  $changed               whether they are published with changes not on the site yet, which can be discarded
     * @param bool                                                  $hasSomethingToPublish whether publishing would change the site
     */
    public function __construct(
        public string $name,
        public ?string $defaultImage,
        public array $mainMenu,
        public array $footerMenu,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public string $status,
        public bool $changed,
        public bool $hasSomethingToPublish,
    ) {}

    public static function fromSettings(Settings $settings): self
    {
        return new self(
            $settings->name()->value(),
            $settings->defaultImage()?->value(),
            $settings->mainMenu()->view(),
            $settings->footerMenu()->view(),
            $settings->timeline()->createdAt(),
            $settings->timeline()->updatedAt(),
            $settings->status()->value,
            $settings->hasUnpublishedChanges(),
            $settings->hasSomethingToPublish(),
        );
    }
}
