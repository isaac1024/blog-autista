<?php

declare(strict_types=1);

namespace Cms\External\Domain;

use Shared\Domain\External\ResourceKind;

/**
 * An audio, such as an episode of a podcast: the show it belongs to, if it
 * tells one.
 */
final readonly class AudioDetails extends ResourceDetails
{
    public const int SHOW_MAX_LENGTH = 200;

    private ?string $show;

    public function __construct(?string $show)
    {
        $this->show = DetailText::line('show', $show, self::SHOW_MAX_LENGTH);
    }

    #[\Override]
    public function kind(): ResourceKind
    {
        return ResourceKind::Audio;
    }

    #[\Override]
    public function assertPublishable(): void {}

    #[\Override]
    public function toArray(): array
    {
        return ['show' => $this->show];
    }
}
