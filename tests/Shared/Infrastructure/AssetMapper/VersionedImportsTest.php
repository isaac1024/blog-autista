<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\AssetMapper;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;

/**
 * The public site has no import map, so a module of its own reaches the
 * modules it imports by their versioned addresses, as AssetMapper serves
 * them, and never by their plain ones, which it answers with a 404.
 */
final class VersionedImportsTest extends KernelTestCase
{
    public function testTheScriptOfTheFacadesImportsItsModulesByTheirVersionedAddresses(): void
    {
        $assets = self::getContainer()->get(AssetMapperInterface::class);
        $script = $assets->getAsset('external.js');
        self::assertNotNull($script);

        preg_match_all("#from '([^']+)'#", (string) $script->content, $imports);

        self::assertSame([
            $assets->getPublicPath('external/consent.js'),
            $assets->getPublicPath('external/frames.js'),
        ], $imports[1]);
        self::assertStringNotContainsString("'./", (string) $script->content);
    }
}
