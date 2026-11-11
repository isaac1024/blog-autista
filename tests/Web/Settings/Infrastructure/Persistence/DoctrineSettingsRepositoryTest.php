<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Tests\Settings\Domain\CmsSettingsPublishedMother as Event;

final class DoctrineSettingsRepositoryTest extends KernelTestCase
{
    use MappedTables;

    public function testItFindsNoSettingsUntilTheCmsPublishesThem(): void
    {
        self::assertNull($this->repository()->find());
    }

    /**
     * Every field comes back as saved: the name at its longest, the image,
     * both menus with and without labels.
     */
    public function testItSavesTheSettingsSoTheyCanBeReadBack(): void
    {
        $menu = array_map(
            static fn (int $page): array => ['page_id' => \sprintf('01a19e72-b07f-7d78-889c-fa4a14a4fc%02d', $page), 'label' => 1 === $page % 2 ? str_repeat('é', 40) : null],
            range(1, 8),
        );
        $settings = Settings::publish(Event::create(str_repeat('á', 60), Event::IMAGE, $menu, array_reverse($menu)));

        $this->repository()->save($settings);
        $this->entityManager()->clear();

        self::assertEquals($settings, $this->repository()->find());
    }

    public function testItSavesSettingsWithoutImageNorMenus(): void
    {
        $settings = Settings::publish(Event::create(defaultImageId: null, mainMenu: [], footerMenu: []));

        $this->repository()->save($settings);
        $this->entityManager()->clear();

        self::assertEquals($settings, $this->repository()->find());
    }

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('web_settings');
    }

    private function repository(): SettingsRepository
    {
        return self::getContainer()->get(SettingsRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
