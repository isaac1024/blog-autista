<?php

declare(strict_types=1);

namespace Web\Redirect\Application;

use Shared\Domain\ImageCopy;
use Shared\Domain\SitePath;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Domain\TargetAddresses;

/**
 * Finds how the site answers an address it has nothing of its own at, by
 * the redirect there, if any.
 *
 * The copies of an image share one redirect, the one of its file, and each
 * width leads to the same width of the target. A redirect to a resource the
 * site doesn't have yet, as it catches up with the CMS, answers nothing.
 */
final readonly class AddressAnswerFinder
{
    /**
     * @param string $path the path the request asks for, as it comes
     */
    public function __construct(private string $path) {}

    public function __invoke(RedirectRepository $redirectRepository, TargetAddresses $targetAddresses): ?AddressAnswer
    {
        $path = SitePath::fromRequest($this->path)?->value();

        if (null === $path) {
            return null;
        }

        $copy = ImageCopy::fromUrl($path);
        $redirect = $redirectRepository->findBySource(null === $copy ? $path : $copy->fileAddress());

        if (null === $redirect) {
            return null;
        }

        $type = $redirect->targetType();
        $id = $redirect->targetId();

        if (null === $type || null === $id) {
            return AddressAnswer::gone();
        }

        $location = $targetAddresses->addressOf($type, $id, $copy?->width());

        return null === $location ? null : AddressAnswer::movedTo($location);
    }
}
