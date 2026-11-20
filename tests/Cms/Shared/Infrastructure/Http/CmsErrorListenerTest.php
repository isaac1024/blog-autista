<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Http;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * What a request asks the CMS wrong is answered with the status of the
 * error, never kept by the browser.
 */
final class CmsErrorListenerTest extends CmsWebTestCase
{
    private const string MISSING = '01a1b835-7cfd-7864-b83b-873347d10bab';

    public function testAPostThatIsNotThereIsNotFound(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', 'http://cms.blogautista.localhost/posts/'.self::MISSING);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertUnstored($client);
    }

    public function testAPreviewLinkExpiredOrChangedIsForbidden(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/preview/posts/'.self::MISSING.'?_expiration=1&_hash=changed');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertUnstored($client);
    }

    public function testAFieldSentAsAListIsABadRequest(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_posts_create', 'http://cms.blogautista.localhost/posts', ['title' => ['x']]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertUnstored($client);
    }

    public function testAnAddressNoRouteAnswersIsNotFoundBeforeSigningIn(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/nowhere', server: ['HTTP_ACCEPT_LANGUAGE' => 'es']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertUnstored($client);
    }

    private static function assertUnstored(KernelBrowser $client): void
    {
        self::assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
    }
}
