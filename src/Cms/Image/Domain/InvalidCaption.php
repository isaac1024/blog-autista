<?php

declare(strict_types=1);

namespace Cms\Image\Domain;

use Shared\Domain\DomainError;

final class InvalidCaption extends DomainError
{
    public static function notASingleParagraph(): self
    {
        return new self('The caption of an image must be a single paragraph.', 'errors.image.caption.not_a_single_paragraph', []);
    }

    public static function tooLong(int $maxLength): self
    {
        return new self(\sprintf('The caption of an image must not exceed %d characters.', $maxLength), 'errors.image.caption.too_long', ['%max%' => $maxLength]);
    }
}
