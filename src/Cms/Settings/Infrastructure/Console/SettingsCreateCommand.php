<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Console;

use Cms\Settings\Application\SettingsCreator;
use Cms\Settings\Domain\SettingsId;
use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cms:settings:create',
    description: 'Creates the settings of the site, which the CMS edits from then on',
)]
final readonly class SettingsCreateCommand
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private EventBus $eventBus,
        private Transaction $transaction,
    ) {}

    public function __invoke(
        SymfonyStyle $style,
        #[Argument(description: 'Name of the blog')]
        string $name,
    ): int {
        try {
            new SettingsCreator(SettingsId::random()->value(), $name)($this->settingsRepository, $this->eventBus, $this->transaction);
        } catch (DomainError $error) {
            $style->error($error->getMessage());

            return Command::FAILURE;
        }

        $style->success('The settings were created.');

        return Command::SUCCESS;
    }
}
