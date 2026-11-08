<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Infrastructure\Console;

use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Domain\SettingsStatus;
use Cms\Settings\Domain\SiteName;
use Cms\Tests\Settings\Domain\SettingsMother;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SettingsCreateCommandTest extends KernelTestCase
{
    private const string NAME = 'cms:settings:create';

    public function testItCreatesTheSettingsAsADraftWithTheName(): void
    {
        $exitCode = $this->command()->execute(['name' => 'blog autista']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $settings = $this->storedSettings();
        self::assertNotNull($settings);
        self::assertEquals(new SiteName('blog autista'), $settings->name());
        self::assertSame(SettingsStatus::Draft, $settings->status());
        self::assertNull($settings->defaultImage());
        self::assertSame([], $settings->mainMenu()->toArray());
        self::assertSame([], $settings->footerMenu()->toArray());
    }

    #[DataProvider('invalidNames')]
    public function testItFailsAndCreatesNothingWhenTheNameIsInvalid(string $name): void
    {
        $exitCode = $this->command()->execute(['name' => $name]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertNull($this->storedSettings());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'too short' => ['a'];
        yield 'too long' => [str_repeat('a', 61)];
    }

    public function testItFailsAndKeepsTheExistingSettingsWhenTheyAlreadyExist(): void
    {
        $existing = SettingsMother::create();
        self::getContainer()->get(SettingsRepository::class)->save($existing);

        $exitCode = $this->command()->execute(['name' => 'Otro nombre']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertEquals($existing, $this->storedSettings());
    }

    private function command(): CommandTester
    {
        return new CommandTester($this->application()->find(self::NAME));
    }

    /**
     * Reuses the booted kernel, so fixtures, command and assertions share one
     * database connection.
     */
    private function application(): Application
    {
        return new Application(self::$kernel ?? self::bootKernel());
    }

    /**
     * Reads the settings back from the database, not from Doctrine's identity map.
     */
    private function storedSettings(): ?Settings
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SettingsRepository::class)->find();
    }
}
