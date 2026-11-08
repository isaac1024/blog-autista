<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Infrastructure\Persistence;

use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\SettingsIdMother;
use Cms\Tests\Settings\Domain\SettingsMother as Mother;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineSettingsRepositoryTest extends KernelTestCase
{
    public function testItFindsNoSettingsBeforeTheyAreCreated(): void
    {
        self::assertNull($this->repository()->find());
    }

    /**
     * Every field comes back as saved: the name at its longest, the image,
     * both menus at their fullest with labels at their longest, and the dates.
     */
    public function testItSavesTheSettingsSoTheyCanBeReadBack(): void
    {
        $menu = array_map(
            static fn (int $page): array => Mother::item(\sprintf('01a19e72-b07f-7d78-889c-fa4a14a4fc%02d', $page), 1 === $page % 2 ? str_repeat('é', 40) : null),
            range(1, 8),
        );
        $settings = Mother::create(
            Mother::version(str_repeat('á', 60), Mother::IMAGE, $menu, array_reverse($menu)),
            createdAt: '2027-01-31 23:59:59',
            updatedAt: '2027-02-01 08:00:00',
        );

        $this->repository()->save($settings);
        $this->entityManager()->clear();

        self::assertEquals($settings, $this->repository()->find());
    }

    /**
     * Published settings keep apart the version on the site, read back with
     * their status.
     */
    public function testItSavesThePublicationSoItCanBeReadBack(): void
    {
        $settings = Mother::published(
            Mother::version(name: 'Otro nombre', mainMenu: [Mother::item(Mother::OTHER_PAGE)]),
            Mother::version(defaultImage: Mother::IMAGE, mainMenu: [Mother::item(label: 'Sobre mí')], footerMenu: [Mother::item(Mother::THIRD_PAGE)]),
        );

        $this->repository()->save($settings);
        $this->entityManager()->clear();

        self::assertEquals($settings, $this->repository()->find());
    }

    public function testItKeepsTheSiteToOneRowOfSettings(): void
    {
        $repository = $this->repository();
        $repository->save(Mother::create());

        $this->expectException(UniqueConstraintViolationException::class);

        $repository->save(Mother::create(id: SettingsIdMother::random()->value()));
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
