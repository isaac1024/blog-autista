<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Shared\Infrastructure\Http\SignedUrls;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The address the CMS serves the image of an external resource at, signed
 * for a while, so its editor sees it, and nobody else.
 */
final readonly class ExternalImageUrl
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SignedUrls $signedUrls,
    ) {}

    /**
     * @param string $image where the file is kept, relative to the image storage
     */
    public function __invoke(string $id, string $image, \DateTimeImmutable $expiresAt): string
    {
        return $this->signedUrls->sign(
            $this->urlGenerator->generate('cms_externals_image', ['id' => $id, 'file' => $image], UrlGeneratorInterface::ABSOLUTE_URL),
            $expiresAt,
        );
    }
}
