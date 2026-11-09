<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

final class CsrfProtectionTest extends CmsWebTestCase
{
    private const string ID = '01a1b835-7cfd-7864-b83b-873347d10bab';

    #[DataProvider('mutations')]
    public function testItRejectsAMissingOrInvalidToken(string $url): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('POST', $url);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('POST', $url, ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mutations(): iterable
    {
        yield 'create page' => ['http://cms.blogautista.localhost/pages'];
        yield 'update page' => ['http://cms.blogautista.localhost/pages/'.self::ID];
        yield 'publish page' => ['http://cms.blogautista.localhost/pages/'.self::ID.'/publish'];
        yield 'unpublish page' => ['http://cms.blogautista.localhost/pages/'.self::ID.'/unpublish'];
        yield 'discard page changes' => ['http://cms.blogautista.localhost/pages/'.self::ID.'/discard'];
        yield 'delete page' => ['http://cms.blogautista.localhost/pages/'.self::ID.'/delete'];
        yield 'create image' => ['http://cms.blogautista.localhost/images'];
        yield 'update image' => ['http://cms.blogautista.localhost/images/'.self::ID];
        yield 'publish image' => ['http://cms.blogautista.localhost/images/'.self::ID.'/publish'];
        yield 'unpublish image' => ['http://cms.blogautista.localhost/images/'.self::ID.'/unpublish'];
        yield 'discard image changes' => ['http://cms.blogautista.localhost/images/'.self::ID.'/discard'];
        yield 'delete image' => ['http://cms.blogautista.localhost/images/'.self::ID.'/delete'];
        yield 'update settings' => ['http://cms.blogautista.localhost/settings'];
        yield 'publish settings' => ['http://cms.blogautista.localhost/settings/publish'];
        yield 'discard settings changes' => ['http://cms.blogautista.localhost/settings/discard'];
    }
}
