<?php

declare(strict_types=1);

namespace Web\Settings\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * The settings of the site, as the CMS last published them: the name of the
 * blog, its default image and its menus. Their image and pages are only ids:
 * the site paints those that are on it, and leaves out the rest.
 *
 * Events can come late or twice, so they only take the ones that happened
 * after the last they took.
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_settings')]
final class Settings
{
    /**
     * @param ?string                                      $defaultImageId null for none
     * @param list<array{page_id: string, label: ?string}> $mainMenu
     * @param list<array{page_id: string, label: ?string}> $footerMenu
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_settings_id')]
        private readonly SettingsId $id,
        #[ORM\Column(length: 60)]
        private string $name,
        #[ORM\Column(length: 36, nullable: true)]
        private ?string $defaultImageId,
        #[ORM\Column(type: 'json')]
        private array $mainMenu,
        #[ORM\Column(type: 'json')]
        private array $footerMenu,
        #[ORM\Column]
        private \DateTimeImmutable $lastEventOn,
    ) {}

    public static function publish(CmsSettingsPublished $event): self
    {
        return new self(
            new SettingsId($event->aggregateId()),
            $event->name(),
            $event->defaultImageId(),
            $event->mainMenu(),
            $event->footerMenu(),
            $event->occurredOn(),
        );
    }

    /**
     * Takes the version the CMS published, unless the settings already took
     * a later event.
     */
    public function republish(CmsSettingsPublished $event): void
    {
        if ($this->lastEventOn > $event->occurredOn()) {
            return;
        }

        $this->name = $event->name();
        $this->defaultImageId = $event->defaultImageId();
        $this->mainMenu = $event->mainMenu();
        $this->footerMenu = $event->footerMenu();
        $this->lastEventOn = $event->occurredOn();
    }

    public function save(SettingsRepository $settingsRepository): void
    {
        $settingsRepository->save($this);
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
}
