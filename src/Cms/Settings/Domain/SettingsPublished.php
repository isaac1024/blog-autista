<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Shared\Domain\Event\RevisedDomainEvent;

/**
 * The settings were put on the site, or their version there replaced, with
 * all the site needs: the name of the blog, its default image, its author
 * and its menus.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class SettingsPublished extends RevisedDomainEvent
{
    /**
     * @param list<array<string, ?string>> $mainMenu   each entry with the id of what it leads to under the key of its kind, such as "page_id", and its label
     * @param list<array<string, ?string>> $footerMenu
     */
    public function __construct(
        string $aggregateId,
        private string $name,
        private ?string $defaultImageId,
        private ?string $authorId,
        private array $mainMenu,
        private array $footerMenu,
        int $revision,
    ) {
        parent::__construct($aggregateId, $revision);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.settings.published';
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'name' => $this->name,
            'default_image_id' => $this->defaultImageId,
            'author_id' => $this->authorId,
            'main_menu' => $this->mainMenu,
            'footer_menu' => $this->footerMenu,
            'revision' => $this->revision(),
        ];
    }
}
