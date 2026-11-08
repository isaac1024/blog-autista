<?php

declare(strict_types=1);

namespace Cms\Tests\Settings\Infrastructure\Controller;

use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Tests\Settings\Domain\SettingsMother as Mother;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class SettingsEditControllerTest extends CmsWebTestCase
{
    private const string URL = 'http://cms.blogautista.localhost/settings';

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', self::URL);

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
    }

    #[DataProvider('settings')]
    public function testItOpensTheEditorOfTheSettings(Settings $settings): void
    {
        $client = self::createAuthenticatedClient();
        self::getContainer()->get(SettingsRepository::class)->save($settings);

        $client->request('GET', self::URL);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_settings');
    }

    /**
     * @return iterable<string, array{Settings}>
     */
    public static function settings(): iterable
    {
        $everything = Mother::version(
            defaultImage: Mother::IMAGE,
            mainMenu: [Mother::item(Mother::PAGE, 'Sobre mí'), Mother::item(Mother::OTHER_PAGE)],
            footerMenu: [Mother::item(Mother::THIRD_PAGE)],
        );

        yield 'a draft with only a name' => [Mother::create()];
        yield 'a draft with everything' => [Mother::create($everything)];
        yield 'published settings with changes' => [Mother::published($everything, Mother::version())];
    }

    public function testItRespondsNotFoundWhileTheSettingsDoNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::URL);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
