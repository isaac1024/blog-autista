<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The domain without its www leads to the same address of the site, for
 * good, whatever is asked there; the hosts of the site and the CMS answer
 * as ever.
 */
final class BareDomainRedirectTest extends WebTestCase
{
    #[DataProvider('addresses')]
    public function testItLeadsTheDomainWithoutWwwToTheSameAddressOfTheSite(string $method, string $address, string $location): void
    {
        $client = self::createClient();

        $client->request($method, $address);

        self::assertResponseRedirects($location, Response::HTTP_MOVED_PERMANENTLY);
        self::assertResponseHeaderSame('Cache-Control', 'max-age=86400, public');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'its home' => ['GET', 'http://blogautista.localhost/', 'http://www.blogautista.localhost/'];
        yield 'a page with its query' => ['GET', 'https://blogautista.localhost/tag/sueno.html?pagina=2', 'https://www.blogautista.localhost/tag/sueno.html?pagina=2'];
        yield 'an address of nothing' => ['HEAD', 'http://blogautista.localhost/wp-login.php', 'http://www.blogautista.localhost/wp-login.php'];
    }

    #[DataProvider('hostsOfTheBlog')]
    public function testItLeavesTheHostsOfTheSiteAndTheCmsAlone(string $address): void
    {
        $client = self::createClient();

        $client->request('GET', $address);

        self::assertNotSame(Response::HTTP_MOVED_PERMANENTLY, $client->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostsOfTheBlog(): iterable
    {
        yield 'the site' => ['http://www.blogautista.localhost/robots.txt'];
        yield 'the CMS' => ['http://cms.blogautista.localhost/login'];
        yield 'another host' => ['http://otro.localhost/robots.txt'];
    }
}
