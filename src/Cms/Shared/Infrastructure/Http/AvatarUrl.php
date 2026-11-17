<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Http;

use Shared\Infrastructure\Http\SignedUrls;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The address the CMS serves the file of an avatar at, signed for a while,
 * so its editor and anyone with a link to its preview see it, and nobody
 * else.
 */
final readonly class AvatarUrl
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SignedUrls $signedUrls,
    ) {}

    /**
     * @param string $avatar where the file is kept, relative to the avatar storage
     */
    public function __invoke(string $id, string $avatar, \DateTimeImmutable $expiresAt): string
    {
        return $this->signedUrls->sign(
            $this->urlGenerator->generate('cms_authors_avatar', ['id' => $id, 'file' => $avatar], UrlGeneratorInterface::ABSOLUTE_URL),
            $expiresAt,
        );
    }
}
