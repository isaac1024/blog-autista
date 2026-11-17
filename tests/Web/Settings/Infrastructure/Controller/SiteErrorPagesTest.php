<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * How the site answers with the page of an error: with the status, and the
 * headers, of the error. Which errors it paints is told by
 * SiteErrorListenerTest.
 */
final class SiteErrorPagesTest extends WebTestCase
{
    private const string HOST = 'http://www.blogautista.localhost';

    public function testItKeepsTheStatusAndHeadersOfTheError(): void
    {
        $client = self::createClient();

        $client->request('POST', self::HOST.'/');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseHeaderSame('Allow', 'GET');
    }

    /**
     * An address of the site with nothing at it may be kept a minute by the
     * page cache, as it is read; asked the wrong way, or of the CMS, it is
     * kept by none.
     */
    public function testItLetsThePageCacheKeepAnAddressOfTheSiteWithNothingAtIt(): void
    {
        $client = self::createClient();

        foreach (['GET', 'HEAD'] as $method) {
            $client->request($method, self::HOST.'/wp-login.php');
            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
            self::assertResponseHeaderSame('Cache-Control', 'max-age=0, public, s-maxage=60');
        }

        $client->request('POST', self::HOST.'/wp-login.php');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertStringNotContainsString('public', (string) $client->getResponse()->headers->get('Cache-Control'));

        $client->request('GET', 'http://cms.blogautista.localhost/wp-login.php');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
    }
}
