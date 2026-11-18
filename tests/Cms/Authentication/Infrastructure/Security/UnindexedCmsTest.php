<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Security;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;

/**
 * Search engines are asked to list nothing of the CMS nor follow its links,
 * whatever it answers; the site is left to its own pages.
 */
final class UnindexedCmsTest extends CmsWebTestCase
{
    public function testItAsksSearchEnginesToListNothingOfTheCms(): void
    {
        $client = self::createClient();

        foreach (['/login', '/pages', '/no-existe', '/robots.txt'] as $path) {
            $client->request('GET', 'http://cms.blogautista.localhost'.$path);

            self::assertSame('noindex,nofollow', $client->getResponse()->headers->get('X-Robots-Tag'), $path);
        }
    }

    public function testItLeavesTheSiteToItsOwnPages(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://www.blogautista.localhost/robots.txt');

        self::assertNotSame('noindex,nofollow', $client->getResponse()->headers->get('X-Robots-Tag'));
    }
}
