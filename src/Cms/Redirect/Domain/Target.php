<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Where a redirect leads: a resource on the site, or nowhere, when what was
 * at its address was taken off for good and the site says it is gone.
 */
#[ORM\Embeddable]
final readonly class Target
{
    private function __construct(
        #[ORM\Column(name: 'target_type', length: 20, nullable: true, enumType: ResourceType::class)]
        private ?ResourceType $type,
        #[ORM\Column(name: 'target_id', type: 'uuid_text', nullable: true)]
        private ?string $id,
    ) {}

    public static function leadingTo(Resource $resource): self
    {
        return new self($resource->type(), $resource->id());
    }

    public static function gone(): self
    {
        return new self(null, null);
    }

    /**
     * The resource it leads to, or null when it says the address is gone.
     */
    public function resource(): ?Resource
    {
        return null === $this->type || null === $this->id ? null : new Resource($this->type, $this->id);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
