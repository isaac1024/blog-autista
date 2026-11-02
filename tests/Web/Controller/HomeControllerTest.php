<?php

declare(strict_types=1);

namespace Web\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testItServesTheWebHost(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://www.blogautista.localhost/');

        self::assertResponseIsSuccessful();
        self::assertSame('Web context is up.', $client->getResponse()->getContent());
    }

    public function testItDoesNotAnswerOnAnUnknownHost(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://unknown.blogautista.localhost/');

        self::assertResponseStatusCodeSame(404);
    }
}
