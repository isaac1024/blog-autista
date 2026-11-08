<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

use Cms\Settings\Domain\SettingsVersion;

/**
 * The settings as the site shows them: the version last published, whatever
 * is being edited since.
 */
final readonly class SettingsOnSiteResponse
{
    /**
     * @param ?string                                      $defaultImage id of the default image; null for none
     * @param list<array{page_id: string, label: ?string}> $mainMenu
     * @param list<array{page_id: string, label: ?string}> $footerMenu
     */
    public function __construct(
        public string $name,
        public ?string $defaultImage,
        public array $mainMenu,
        public array $footerMenu,
    ) {}

    public static function fromVersion(SettingsVersion $version): self
    {
        return new self(
            $version->name()->value(),
            $version->defaultImage()?->value(),
            $version->mainMenu()->toArray(),
            $version->footerMenu()->toArray(),
        );
    }
}
