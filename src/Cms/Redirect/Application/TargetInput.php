<?php

declare(strict_types=1);

namespace Cms\Redirect\Application;

use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\Target;

/**
 * Where an editor says a redirect leads, as primitives: a resource by its
 * type and id, or no type at all for an address that is gone.
 */
final readonly class TargetInput
{
    public function __construct(
        private ?string $type,
        private ?string $id,
    ) {}

    public function target(): Target
    {
        if (null === $this->type || '' === $this->type) {
            return Target::gone();
        }

        $type = ResourceType::tryFrom($this->type) ?? throw InvalidRedirect::unknownTargetType($this->type);

        if (null === $this->id || '' === $this->id) {
            throw InvalidRedirect::missingTarget();
        }

        return Target::leadingTo(new Resource($type, $this->id));
    }
}
