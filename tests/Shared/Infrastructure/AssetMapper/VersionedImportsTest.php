<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\AssetMapper;

use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\AssetMapper\VersionedImports;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * The public site has no import map, so a module of its own reaches the
 * modules it imports by their versioned addresses, as AssetMapper serves
 * them, and never by their plain ones, which it answers with a 404.
 *
 * The compiled scripts are cached, so the compiler is also run by hand on
 * the imports AssetMapper finds, to see what it does with each.
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
            $assets->getPublicPath('external/heights.js'),
        ], $imports[1]);
        self::assertStringNotContainsString("'./", (string) $script->content);
    }

    public function testItCompilesOnlyScripts(): void
    {
        $compiler = new VersionedImports();

        self::assertTrue($compiler->supports(new MappedAsset('external.js', publicPathWithoutDigest: '/assets/external.js')));
        self::assertFalse($compiler->supports(new MappedAsset('styles/app.css', publicPathWithoutDigest: '/assets/styles/app.css')));
    }

    /**
     * Whatever quotes it is written in, beside the module or above it; the
     * bare imports, which the import map of the CMS resolves, and those of
     * modules AssetMapper doesn't serve, stay as they are.
     */
    #[DataProvider('scripts')]
    public function testItWritesEachRelativeImportByTheVersionedAddressOfItsModule(string $content, string $compiled): void
    {
        $asset = new MappedAsset('external/embed.js', publicPathWithoutDigest: '/assets/external/embed.js', javaScriptImports: [
            new JavaScriptImport('/assets/external/consent.js', 'external/consent.js', '/app/assets/external/consent.js', addImplicitlyToImportMap: true),
            new JavaScriptImport('/assets/shared/http.js', 'shared/http.js', '/app/assets/shared/http.js', addImplicitlyToImportMap: true),
            new JavaScriptImport('@hotwired/stimulus', 'vendor/stimulus.js', '/app/assets/vendor/stimulus.js'),
            new JavaScriptImport('/assets/external/missing.js', 'external/missing.js', '/app/assets/external/missing.js', addImplicitlyToImportMap: true),
        ]);
        $assets = self::createStub(AssetMapperInterface::class);
        $assets->method('getPublicPath')->willReturnCallback(static fn (string $path): ?string => [
            'external/consent.js' => '/assets/external/consent-B9Ex8Sd.js',
            'shared/http.js' => '/assets/shared/http-Xy12AbC.js',
            'vendor/stimulus.js' => '/assets/vendor/stimulus-Q1w2E3r.js',
        ][$path] ?? null);

        self::assertSame($compiled, new VersionedImports()->compile($content, $asset, $assets));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function scripts(): iterable
    {
        yield 'beside it, in single quotes' => ["import { a } from './consent.js';", "import { a } from '/assets/external/consent-B9Ex8Sd.js';"];
        yield 'beside it, in double quotes' => ['import { a } from "./consent.js";', 'import { a } from "/assets/external/consent-B9Ex8Sd.js";'];
        yield 'beside it, in backticks, as a dynamic import' => ['await import(`./consent.js`);', 'await import(`/assets/external/consent-B9Ex8Sd.js`);'];
        yield 'above it' => ["import { get } from '../shared/http.js';", "import { get } from '/assets/shared/http-Xy12AbC.js';"];
        yield 'a bare one' => ["import { Controller } from '@hotwired/stimulus';", "import { Controller } from '@hotwired/stimulus';"];
        yield 'one of a module not served' => ["import { b } from './missing.js';", "import { b } from './missing.js';"];
        yield 'a text that only looks like one' => ['const path = "consent.js";', 'const path = "consent.js";'];
    }
}
