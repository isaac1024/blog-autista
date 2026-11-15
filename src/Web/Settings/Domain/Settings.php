<?php

declare(strict_types=1);

namespace Web\Settings\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * The settings of the site, as the CMS last published them: the name of the
 * blog, its default image and its menus. Their image and pages are only ids:
 * the site paints those that are on it, and leaves out the rest.
 *
 * Events can come late or twice: the projection that hands them over only
 * lets through those newer than the last it applied, by their revision.
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_settings')]
final class Settings
{
    /**
     * @param ?string                 $defaultImageId null for none
     * @param array<array-key, mixed> $mainMenu       its entries, as kept, which MenuEntries reads
     * @param array<array-key, mixed> $footerMenu
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
    ) {}

    public static function publish(CmsSettingsPublished $event): self
    {
        return new self(
            new SettingsId($event->aggregateId()),
            $event->name(),
            $event->defaultImageId(),
            $event->mainMenu(),
            $event->footerMenu(),
        );
    }

    /**
     * Takes the version the CMS published.
     */
    public function republish(CmsSettingsPublished $event): void
    {
        $this->name = $event->name();
        $this->defaultImageId = $event->defaultImageId();
        $this->mainMenu = $event->mainMenu();
        $this->footerMenu = $event->footerMenu();
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
     * @return list<array{type: string, id: string, label: ?string}>
     */
    public function mainMenu(): array
    {
        return MenuEntries::read($this->mainMenu);
    }

    /**
     * @return list<array{type: string, id: string, label: ?string}>
     */
    public function footerMenu(): array
    {
        return MenuEntries::read($this->footerMenu);
    }
}
