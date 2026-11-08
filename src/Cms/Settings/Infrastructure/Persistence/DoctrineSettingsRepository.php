<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Persistence;

use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that persists and reads the settings through Doctrine ORM.
 */
#[AsAlias(SettingsRepository::class)]
final readonly class DoctrineSettingsRepository implements SettingsRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Settings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(): ?Settings
    {
        return $this->entityManager->getRepository(Settings::class)->findOneBy([]);
    }
}
