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
}
