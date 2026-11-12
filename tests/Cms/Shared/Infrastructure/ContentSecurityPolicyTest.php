<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure;

/**
 * Only the CMS's own code runs on its pages: the import map, its one inline
 * script, carries the nonce of the request, and nothing else inline runs.
 */
final class ContentSecurityPolicyTest extends CmsWebTestCase
{
    public function testItRunsOnlyItsOwnScriptsAndTheImportMapOfTheRequest(): void
    {
        $client = self::createAuthenticatedClient();
        $client->request('GET', 'http://cms.blogautista.localhost/pages');

        self::assertResponseIsSuccessful();
        $policy = (string) $client->getResponse()->headers->get('Content-Security-Policy');

        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\\/=]+'(;|$)/", $policy);
        self::assertStringNotContainsString('unsafe-inline', $policy);
        self::assertStringNotContainsString('unsafe-eval', $policy);
        self::assertStringContainsString("object-src 'none'", $policy);
        self::assertStringContainsString("base-uri 'none'", $policy);
        self::assertStringContainsString("frame-ancestors 'none'", $policy);
    }

    public function testEachRequestHasANonceOfItsOwn(): void
    {
        $client = self::createAuthenticatedClient();
        $client->request('GET', 'http://cms.blogautista.localhost/pages');
        $first = $client->getResponse()->headers->get('Content-Security-Policy');
        $client->request('GET', 'http://cms.blogautista.localhost/pages');

        self::assertNotSame($first, $client->getResponse()->headers->get('Content-Security-Policy'));
    }
}
