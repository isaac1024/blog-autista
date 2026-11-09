<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Infrastructure\Controller;

use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\SettingsIdMother;
use Cms\Tests\Settings\Domain\SettingsMother as Mother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final class SettingsPublishControllerTest extends CmsWebTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    private const string URL = 'http://cms.blogautista.localhost/settings/publish';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();
        $settings = $this->saveSettings(Mother::create());

        $this->post($client, 'cms_settings_publish', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertEquals($settings, $this->storedSettings());
        self::assertSame([], $this->queuedEvents());
    }

    public function testItPublishesTheSavedVersionAndGoesBackToTheEditor(): void
    {
        $client = self::createAuthenticatedClient();
        $version = Mother::version(defaultImage: Mother::IMAGE, mainMenu: [Mother::item(label: 'Sobre mí')]);
        $this->saveSettings(Mother::create($version));

        $this->post($client, 'cms_settings_publish', self::URL);

        self::assertResponseRedirects('/settings', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.settings.published', 'parameters' => []]], self::successNotices($client));
        self::assertSame([], self::notices($client));
        self::assertEquals(Mother::published($version), $this->storedSettings());
    }

    public function testItQueuesThePublicationForTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveSettings(Mother::create(Mother::version(
            defaultImage: Mother::IMAGE,
            mainMenu: [Mother::item(label: 'Sobre mí')],
            footerMenu: [Mother::item(Mother::OTHER_PAGE)],
        )));

        $this->post($client, 'cms_settings_publish', self::URL);

        $events = $this->queuedEvents();
        self::assertCount(1, $events);
        self::assertSame('cms.settings.published', $events[0]->name);
        self::assertSame(SettingsIdMother::DEFAULT_VALUE, $events[0]->aggregateId);
        self::assertSame([
            'name' => Mother::NAME,
            'default_image_id' => Mother::IMAGE,
            'main_menu' => [Mother::item(label: 'Sobre mí')],
            'footer_menu' => [Mother::item(Mother::OTHER_PAGE)],
            'revision' => 1,
        ], $events[0]->body);
    }

    public function testItGoesBackToTheEditorToTellWhyWhenThereIsNothingToPublish(): void
    {
        $client = self::createAuthenticatedClient();
        $settings = $this->saveSettings(Mother::published());

        $this->post($client, 'cms_settings_publish', self::URL);

        self::assertResponseRedirects('/settings', Response::HTTP_SEE_OTHER);
        self::assertSame([], self::successNotices($client));
        self::assertSame([['key' => 'errors.settings.publication.nothing_to_publish', 'parameters' => []]], self::notices($client));
        self::assertEquals($settings, $this->storedSettings());
        self::assertSame([], $this->queuedEvents());
    }

    public function testItRespondsNotFoundWhileTheSettingsDoNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_settings_publish', self::URL);

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
