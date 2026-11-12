<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The public site runs only its own scripts and needs no nonce, so its
 * policy is the same on every page and the page cache keeps it as it is.
 */
final class ContentSecurityPolicyTest extends WebTestCase
{
    public function testEveryPageHasTheSamePolicyWithoutANonce(): void
    {
        $client = self::createClient();
        $client->request('GET', 'http://www.blogautista.localhost/not-a-page.html');
        $first = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        $client->request('GET', 'http://www.blogautista.localhost/another-one.html');

        self::assertSame($first, $client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertStringNotContainsString('nonce-', $first);
        self::assertStringNotContainsString('unsafe-inline', $first);
        self::assertStringContainsString("default-src 'self'", $first);
        self::assertStringContainsString("object-src 'none'", $first);
        self::assertStringContainsString("frame-ancestors 'none'", $first);
    }
}
