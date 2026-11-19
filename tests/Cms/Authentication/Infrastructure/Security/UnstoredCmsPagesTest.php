<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Security;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;

/**
 * Going back after signing out asks the CMS again instead of showing the
 * page the browser kept, so no page of the CMS may be stored.
 */
final class UnstoredCmsPagesTest extends CmsWebTestCase
{
    public function testItKeepsThePagesOfTheAdministratorOutOfTheBrowserStores(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', 'http://cms.blogautista.localhost/pages');

        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->headers->getCacheControlDirective('no-store'));
        self::assertTrue($client->getResponse()->headers->getCacheControlDirective('private'));
    }

    /**
     * What a page asks the CMS, such as the images a search finds, is the
     * administrator's as much as the page.
     */
    public function testItKeepsWhatThePagesAskOutOfTheBrowserStores(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', 'http://cms.blogautista.localhost/images/search');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertTrue($client->getResponse()->headers->getCacheControlDirective('no-store'));
        self::assertTrue($client->getResponse()->headers->getCacheControlDirective('private'));
    }

    public function testItKeepsTheRedirectToTheLoginOutOfTheBrowserStores(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/pages');

        self::assertResponseRedirects('http://cms.blogautista.localhost/login');
        self::assertTrue($client->getResponse()->headers->getCacheControlDirective('no-store'));
    }

    public function testItLeavesThePagesOfTheSiteToTheirCache(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://www.blogautista.localhost/no-existe.html');

        self::assertResponseStatusCodeSame(404);
        self::assertFalse($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }
}
