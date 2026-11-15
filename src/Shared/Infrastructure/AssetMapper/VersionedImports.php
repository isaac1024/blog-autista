<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AssetMapper;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Filesystem\Path;

/**
 * Has a module import the modules beside it by their versioned addresses,
 * such as /assets/external/consent-B9Ex8Sd.js, rather than by plain relative
 * ones, which AssetMapper doesn't serve: the CMS maps those in its import
 * map, but the public site has none, so that Varnish can cache its pages
 * without a nonce.
 *
 * It runs after AssetMapper's own compiler, which finds those imports and
 * writes each relative to the module, as this then finds it. A module is
 * versioned after what it says, so after the versions of what it imports.
 */
#[AsTaggedItem(priority: -100)]
final readonly class VersionedImports implements AssetCompilerInterface
{
    #[\Override]
    public function supports(MappedAsset $asset): bool
    {
        return 'js' === $asset->publicExtension;
    }

    #[\Override]
    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        $replacements = [];

        foreach ($asset->getJavaScriptImports() as $import) {
            $publicPath = $assetMapper->getPublicPath($import->assetLogicalPath);

            // Only the relative imports AssetMapper rewrote; bare ones go through an import map.
            if (!$import->addImplicitlyToImportMap || null === $publicPath) {
                continue;
            }

            $relative = Path::makeRelative($import->importName, \dirname($asset->publicPathWithoutDigest));
            $relative = str_starts_with($relative, '../') ? $relative : './'.$relative;

            foreach (["'", '"', '`'] as $quote) {
                $replacements[$quote.$relative.$quote] = $quote.$publicPath.$quote;
            }
        }

        return strtr($content, $replacements);
    }
}
