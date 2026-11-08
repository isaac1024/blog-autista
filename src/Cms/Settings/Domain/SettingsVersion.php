<?php

declare(strict_types=1);

namespace Cms\Settings\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * What an editor writes in the settings: the name of the blog, its default
 * image and its two menus. The one being edited and the one on the site are
 * both versions, so the site keeps its version while the settings are edited.
 *
 * The default image and the pages of the menus are optional, and needn't be
 * on the site: the site paints only what is.
 */
#[ORM\Embeddable]
final readonly class SettingsVersion
{
    public function __construct(
        #[ORM\Embedded(class: SiteName::class, columnPrefix: false)]
        private SiteName $name,
        /*
         * The image the site shares a page with when it has none of its own.
         */
        #[ORM\Column(name: 'default_image_id', type: 'cms_settings_image_id', nullable: true)]
        private ?ImageId $defaultImage,
        #[ORM\Column(name: 'main_menu', type: 'cms_settings_menu')]
        private Menu $mainMenu,
        #[ORM\Column(name: 'footer_menu', type: 'cms_settings_menu')]
        private Menu $footerMenu,
    ) {}

    /**
     * A version from what an editor writes; a blank image means none.
     *
     * @param array<array-key, mixed> $mainMenu
     * @param array<array-key, mixed> $footerMenu
     */
    public static function fromPrimitives(string $name, string $defaultImage, array $mainMenu, array $footerMenu): self
    {
        return new self(
            new SiteName($name),
            '' === $defaultImage ? null : new ImageId($defaultImage),
            Menu::fromArray($mainMenu),
            Menu::fromArray($footerMenu),
        );
    }

    public function equals(self $other): bool
    {
        return $this->name->value() === $other->name->value()
            && $this->defaultImage?->value() === $other->defaultImage?->value()
            && $this->mainMenu->equals($other->mainMenu)
            && $this->footerMenu->equals($other->footerMenu);
    }

    public function name(): SiteName
    {
        return $this->name;
    }

    public function defaultImage(): ?ImageId
    {
        return $this->defaultImage;
    }

    public function mainMenu(): Menu
    {
        return $this->mainMenu;
    }

    public function footerMenu(): Menu
    {
        return $this->footerMenu;
    }

    /**
     * The images it uses: its default one, if any.
     *
     * @return list<string>
     */
    public function imageIds(): array
    {
        return null === $this->defaultImage ? [] : [$this->defaultImage->value()];
    }

    /**
     * The pages its menus lead to.
     *
     * @return list<string>
     */
    public function pageIds(): array
    {
        return [...$this->mainMenu->pageIds(), ...$this->footerMenu->pageIds()];
    }
}
