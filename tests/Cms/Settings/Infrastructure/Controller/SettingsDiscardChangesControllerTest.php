<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Infrastructure\Controller;

use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\SettingsMother as Mother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class SettingsDiscardChangesControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/settings/discard';

    private const string BEFORE = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $settings = $this->saveSettings(Mother::published(Mother::version(name: 'Otro nombre'), Mother::version()));

        $this->post($client, 'cms_settings_discard', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($settings, $this->storedSettings());
    }

    public function testItBringsBackTheVersionOnTheSiteAndGoesBackToTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveSettings(Mother::published(Mother::version(name: 'Otro nombre'), Mother::version(), createdAt: self::BEFORE));

        $this->post($client, 'cms_settings_discard', self::URL);

        self::assertResponseRedirects('/settings', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.settings.discarded', 'parameters' => []]], self::successNotices($client));
        self::assertSame([], self::notices($client));
        self::assertEquals(Mother::published(createdAt: self::BEFORE, updatedAt: ClockMother::NOW), $this->storedSettings());
        self::assertSame([], $this->queuedEvents());
    }

    #[DataProvider('settingsWithoutChanges')]
    public function testItGoesBackToTheEditorToTellWhyAndKeepsTheSettings(Settings $settings): void
    {
        $client = self::createAuthenticatedClient();
        $expected = $this->saveSettings($settings);

        $this->post($client, 'cms_settings_discard', self::URL);

        self::assertResponseRedirects('/settings', Response::HTTP_SEE_OTHER);
        self::assertSame([], self::successNotices($client));
        self::assertSame([['key' => 'errors.settings.publication.no_changes_to_discard', 'parameters' => []]], self::notices($client));
        self::assertEquals($expected, $this->storedSettings());
    }

    /**
     * @return iterable<string, array{Settings}>
     */
    public static function settingsWithoutChanges(): iterable
    {
        yield 'a draft' => [Mother::create()];
        yield 'published settings as they are on the site' => [Mother::published()];
    }

    public function testItRespondsNotFoundWhileTheSettingsDoNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_settings_discard', self::URL);

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
