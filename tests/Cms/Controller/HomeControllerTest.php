<?php

declare(strict_types=1);

namespace Cms\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testItServesTheCmsHost(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/');

        self::assertResponseIsSuccessful();
        self::assertSame('CMS context is up.', $client->getResponse()->getContent());
    }

    public function testItDoesNotAnswerOnAnUnknownHost(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://admin.blogautista.localhost/');

        self::assertResponseStatusCodeSame(404);
    }
}
