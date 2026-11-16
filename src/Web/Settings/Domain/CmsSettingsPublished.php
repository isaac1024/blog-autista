<?php

declare(strict_types=1);

namespace Web\Settings\Domain;

use Shared\Domain\Event\EventBody;
use Shared\Domain\Event\RevisedReceivedEvent;

/**
 * The CMS put its settings on the site, or replaced their version there, with
 * all the site needs: the name of the blog, its default image, its author
 * and its menus.
 * The site declares it on its own, by the name the CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsSettingsPublished extends RevisedReceivedEvent
{
    /**
     * @param ?string                                               $defaultImageId null for none
     * @param ?string                                               $authorId       null for none
     * @param list<array{type: string, id: string, label: ?string}> $mainMenu
     * @param list<array{type: string, id: string, label: ?string}> $footerMenu
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
    public static function fromPrimitives(string $aggregateId, array $body): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->string('name'),
            $body->nullableString('default_image_id'),
            // Settings published before they had an author name none.
            $body->nullableString('author_id'),
            MenuEntries::read($body->array('main_menu')),
            MenuEntries::read($body->array('footer_menu')),
            $body->integer('revision'),
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function defaultImageId(): ?string
    {
        return $this->defaultImageId;
    }

    public function authorId(): ?string
    {
        return $this->authorId;
    }

    /**
     * @return list<array{type: string, id: string, label: ?string}>
     */
    public function mainMenu(): array
    {
        return $this->mainMenu;
    }

    /**
     * @return list<array{type: string, id: string, label: ?string}>
     */
    public function footerMenu(): array
    {
        return $this->footerMenu;
    }
}
