<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Infrastructure\Controller;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\SettingsMother as Mother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class SettingsUpdateControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;

    private const string URL = 'http://cms.blogautista.localhost/settings';

    /**
     * When the settings edited here were created and last changed.
     */
    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        // The domain dates what happens, so it is dated as the settings compared with.
        self::mockTime(ClockMother::now());
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $this->post($client, 'cms_settings_update', self::URL, ['name' => 'Otro nombre']);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    /**
     * The menus post each entry under its position, as the editor renumbers
     * them; positions it skipped after removing an entry don't matter.
     */
    public function testItSavesTheSettingsAndGoesBackToTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveSettings(Mother::create(createdAt: self::BEFORE));

        $this->post($client, 'cms_settings_update', self::URL, [
            'name' => 'Otro nombre',
            'default_image' => Mother::IMAGE,
            'author' => '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02',
            'main_menu' => [
                ['page_id' => Mother::PAGE, 'label' => 'Sobre mí'],
                ['page_id' => Mother::OTHER_PAGE, 'label' => ''],
            ],
            'footer_menu' => [3 => ['page_id' => Mother::THIRD_PAGE, 'label' => '']],
        ]);

        self::assertResponseRedirects('/settings', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.settings.saved', 'parameters' => []]], self::successNotices($client));
        self::assertSame([], self::notices($client));
        self::assertEquals(
            Mother::create(
                Mother::version(
                    'Otro nombre',
                    Mother::IMAGE,
                    [Mother::item(Mother::PAGE, 'Sobre mí'), Mother::item(Mother::OTHER_PAGE)],
                    [Mother::item(Mother::THIRD_PAGE)],
                    '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02',
                ),
                createdAt: self::BEFORE,
                updatedAt: ClockMother::NOW,
            ),
            $this->storedSettings(),
        );
    }

    /**
     * An empty menu posts no field at all, and a blank image means none.
     */
    public function testItEmptiesTheMenusAndTheImageWhenNothingIsPosted(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveSettings(Mother::create(Mother::version(defaultImage: Mother::IMAGE, mainMenu: [Mother::item()]), createdAt: self::BEFORE));

        $this->post($client, 'cms_settings_update', self::URL, ['name' => Mother::NAME, 'default_image' => '']);

        self::assertResponseRedirects('/settings', Response::HTTP_SEE_OTHER);
        self::assertEquals(Mother::create(createdAt: self::BEFORE, updatedAt: ClockMother::NOW), $this->storedSettings());
    }

    /**
     * The image and the pages it uses can't be deleted while it uses them.
     */
    public function testItKeepsTrackOfTheImageAndThePagesItUses(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveSettings(Mother::create());

        $this->post($client, 'cms_settings_update', self::URL, [
            'name' => Mother::NAME,
            'default_image' => Mother::IMAGE,
            'main_menu' => [['page_id' => Mother::PAGE, 'label' => '']],
            'footer_menu' => [['page_id' => Mother::OTHER_PAGE, 'label' => '']],
        ]);

        $relations = self::getContainer()->get(RelationRepository::class);
        self::assertTrue($relations->isUsed(ResourceType::Image, Mother::IMAGE));
        self::assertTrue($relations->isUsed(ResourceType::Page, Mother::PAGE));
        self::assertTrue($relations->isUsed(ResourceType::Page, Mother::OTHER_PAGE));
    }

    /**
     * The editor tells why, from the key and parameters of the error.
     *
     * @param array<string, mixed>                                      $fields
     * @param array{key: string, parameters: array<string, int|string>} $notice
     */
    #[DataProvider('invalidFields')]
    public function testItGoesBackToTheEditorToTellWhyAndKeepsTheSettings(array $fields, array $notice): void
    {
        $client = self::createAuthenticatedClient();
        $settings = $this->saveSettings(Mother::create(createdAt: self::BEFORE));

        $this->post($client, 'cms_settings_update', self::URL, $fields);

        self::assertResponseRedirects('/settings', Response::HTTP_SEE_OTHER);
        self::assertSame([], self::successNotices($client));
        self::assertSame([$notice], self::notices($client));
        self::assertEquals($settings, $this->storedSettings());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array{key: string, parameters: array<string, int|string>}}>
     */
    public static function invalidFields(): iterable
    {
        yield 'blank name' => [['name' => ' '], ['key' => 'errors.settings.name.too_short', 'parameters' => ['%min%' => 2]]];
        yield 'missing name' => [[], ['key' => 'errors.settings.name.too_short', 'parameters' => ['%min%' => 2]]];
        yield 'image that is not an id' => [['name' => Mother::NAME, 'default_image' => 'rectangulo'], ['key' => 'errors.uuid.invalid', 'parameters' => ['%value%' => 'rectangulo']]];
        yield 'a main menu longer than the header holds' => [
            ['name' => Mother::NAME, 'main_menu' => array_map(static fn (int $page): array => ['page_id' => \sprintf('01a19e72-b07f-7d78-889c-fa4a14a4fc%02d', $page), 'label' => ''], range(1, 7))],
            ['key' => 'errors.settings.menu.too_many_main_items', 'parameters' => ['%max%' => 6]],
        ];
    }

    public function testItRespondsNotFoundWhileTheSettingsDoNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_settings_update', self::URL, ['name' => Mother::NAME]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Keeps the client's kernel between requests so the fixture, the request
     * and the assertions share one database connection.
     */
    #[\Override]
    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = parent::createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    private function saveSettings(Settings $settings): Settings
    {
        self::getContainer()->get(SettingsRepository::class)->save($settings);

        return clone $settings;
    }

    /**
     * Reads the settings back from the database, not from Doctrine's identity map.
     */
    private function storedSettings(): ?Settings
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(SettingsRepository::class)->find();
    }

    /**
     * The notices flashed for the page it goes back to, left for it to show.
     *
     * @return array<mixed>
     */
    private static function notices(KernelBrowser $client): array
    {
        $flashes = $client->getRequest()->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashes);

        return $flashes->peek('error');
    }
}
