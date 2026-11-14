<?php

declare(strict_types=1);

namespace Web\Analytics\Application;

use Shared\Domain\DomainError;
use Web\Analytics\Domain\Content;

final class ContentNotPublished extends DomainError
{
    public static function fromContent(Content $content): self
    {
        $id = $content->id()->value();

        return new self(
            \sprintf('Nothing of the kind "%s" on the site has the id "%s".', $content->type()->value, $id),
            'errors.visit.content_not_published',
            ['%type%' => $content->type()->value, '%id%' => $id],
        );
    }
}
