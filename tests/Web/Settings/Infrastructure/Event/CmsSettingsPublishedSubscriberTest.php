<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Infrastructure\Event;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Tests\Settings\Domain\CmsSettingsPublishedMother as Event;

/**
 * The settings the CMS publishes, through the events as they arrive.
 */
final class CmsSettingsPublishedSubscriberTest extends KernelTestCase
{
    public function testItPutsOnTheSiteTheSettingsTheCmsPublishes(): void
    {
        $menu = [['page_id' => Event::PAGE, 'label' => 'Sobre mí'], ['page_id' => Event::OTHER_PAGE, 'label' => null]];

        $this->handle(Event::body(mainMenu: $menu, footerMenu: array_reverse($menu)));

        self::assertEquals(Settings::publish(Event::create(mainMenu: $menu, footerMenu: array_reverse($menu))), $this->storedSettings());
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    #[DataProvider('malformedEntries')]
    public function testItTakesNoMenuWithAMalformedEntry(array $entry): void
    {
        try {
            $this->handle(['main_menu' => [['page_id' => Event::PAGE, 'label' => null], $entry]] + Event::body());
            self::fail('A malformed menu entry was taken.');
        } catch (\UnexpectedValueException) {
            self::assertNull($this->storedSettings());
        }
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function malformedEntries(): iterable
    {
        yield 'without a page' => [['label' => 'Sobre mí']];
        yield 'with a page that is not text' => [['page_id' => 1, 'label' => null]];
        yield 'with a label that is not text' => [['page_id' => Event::OTHER_PAGE, 'label' => ['Sobre mí']]];
    }

    public function testItTakesSettingsWithoutImageNorMenus(): void
    {
        $this->handle(Event::body(defaultImageId: null, mainMenu: [], footerMenu: []));

        self::assertEquals(Settings::publish(Event::create(defaultImageId: null, mainMenu: [], footerMenu: [])), $this->storedSettings());
    }

    public function testItReplacesTheSettingsWithTheVersionTheCmsPublishesLater(): void
    {
        $this->handle(Event::body());

        $this->handle(Event::body(name: 'Otro nombre', mainMenu: []), 2);

        self::assertEquals(Settings::publish(Event::create(name: 'Otro nombre', mainMenu: [])), $this->storedSettings());
    }

    public function testItKeepsTheSettingsWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle(Event::body(name: 'Otro nombre'), 2);

        $this->handle(Event::body());

        self::assertEquals(Settings::publish(Event::create(name: 'Otro nombre')), $this->storedSettings());
    }

    public function testItUsesTheRevisionToOrderVersionsFromTheSameSecond(): void
    {
        $this->handle(Event::body());

        $this->handle(Event::body(name: 'Otro nombre'), revision: 2);

        self::assertSame('Otro nombre', $this->storedSettings()?->name());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body, int $revision = 1): void
    {
        $body['revision'] = $revision;

        // As the worker does after each message, so the projection reads
        // what it keeps from the database, not from Doctrine's memory.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage(
            'cms.settings.published',
            Event::ID,
            $body,
        ));
    }

    private function storedSettings(): ?Settings
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SettingsRepository::class)->find();
    }
}
