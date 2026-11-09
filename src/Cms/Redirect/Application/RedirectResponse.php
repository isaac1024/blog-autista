<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\Redirect;

/**
 * A redirect as the CMS shows it.
 *
 * It carries everything an editor shows, one parameter each.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class RedirectResponse
{
    /**
     * @param string  $source     the address to show: the largest copy, for an image
     * @param bool    $image      whether it is the address of an image, which only leads to images
     * @param ?string $targetType one of the ResourceType values, null when the address is gone
     * @param ?string $originType one of the ResourceType values, null when no resource is known to have been there
     */
    public function __construct(
        public string $id,
        public string $source,
        public bool $image,
        public ?string $targetType,
        public ?string $targetId,
        public ?string $originType,
        public ?string $originId,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function fromRedirect(Redirect $redirect): self
    {
        $target = $redirect->target()->resource();
        $origin = $redirect->origin()->resource();

        return new self(
            $redirect->id()->value(),
            $redirect->source()->shown(),
            $redirect->source()->isImage(),
            $target?->type()->value,
            $target?->id(),
            $origin?->type()->value,
            $origin?->id(),
            $redirect->timeline()->createdAt(),
            $redirect->timeline()->updatedAt(),
        );
    }
}
