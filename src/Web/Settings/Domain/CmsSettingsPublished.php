<?php

declare(strict_types=1);

namespace Web\Settings\Domain;

use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBody;

/**
 * The CMS put its settings on the site, or replaced their version there, with
 * all the site needs: the name of the blog, its default image and its menus.
 * The site declares it on its own, by the name the CMS publishes it under.
 *
 * It carries what the site shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class CmsSettingsPublished extends DomainEvent
{
    /**
     * @param ?string                                      $defaultImageId null for none
     * @param list<array{page_id: string, label: ?string}> $mainMenu
     * @param list<array{page_id: string, label: ?string}> $footerMenu
     */
    public function __construct(
        string $aggregateId,
        private string $name,
        private ?string $defaultImageId,
        private array $mainMenu,
        private array $footerMenu,
        string $eventId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($aggregateId, $eventId, $occurredOn);
    }

    #[\Override]
    public static function eventName(): string
    {
        return 'cms.settings.published';
    }

    #[\Override]
    public static function fromPrimitives(string $aggregateId, array $body, string $eventId, \DateTimeImmutable $occurredOn): static
    {
        $body = new EventBody($body);

        return new static(
            $aggregateId,
            $body->string('name'),
            $body->nullableString('default_image_id'),
            self::menu($body->array('main_menu')),
            self::menu($body->array('footer_menu')),
            $eventId,
            $occurredOn,
        );
    }

    #[\Override]
    public function toPrimitives(): array
    {
        return [
            'name' => $this->name,
            'default_image_id' => $this->defaultImageId,
            'main_menu' => $this->mainMenu,
            'footer_menu' => $this->footerMenu,
        ];
    }

    public function name(): string
    {
        return $this->name;
    }

    public function defaultImageId(): ?string
    {
        return $this->defaultImageId;
    }

    /**
     * @return list<array{page_id: string, label: ?string}>
     */
    public function mainMenu(): array
    {
        return $this->mainMenu;
    }

    /**
     * @return list<array{page_id: string, label: ?string}>
     */
    public function footerMenu(): array
    {
        return $this->footerMenu;
    }

    /**
     * @param array<array-key, mixed> $items
     *
     * @return list<array{page_id: string, label: ?string}>
     */
    private static function menu(array $items): array
    {
        $menu = [];

        foreach ($items as $item) {
            $pageId = \is_array($item) ? $item['page_id'] ?? null : null;
            $label = \is_array($item) ? $item['label'] ?? null : null;

            if (!\is_string($pageId) || (null !== $label && !\is_string($label))) {
                throw new \UnexpectedValueException('The event body has a menu entry without a "page_id" string or a "label" string or null.');
            }

            $menu[] = ['page_id' => $pageId, 'label' => $label];
        }

        return $menu;
    }
}
