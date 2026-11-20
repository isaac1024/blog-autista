<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * The resource off the site whose last address a redirect answers, if any:
 * what was there. While it has one, whatever links to or embeds that
 * resource is painted with the target instead, or not at all when it is
 * gone.
 *
 * The CMS works it out; editors never write it. A resource has it on one
 * redirect at most: the one of its last address, until it is back on the
 * site.
 */
#[ORM\Embeddable]
final readonly class Origin
{
    private function __construct(
        #[ORM\Column(name: 'origin_type', length: 20, nullable: true, enumType: ResourceType::class)]
        private ?ResourceType $type,
        #[ORM\Column(name: 'origin_id', type: 'uuid_text', nullable: true)]
        private ?string $id,
    ) {}

    public static function from(Resource $resource): self
    {
        return new self($resource->type(), $resource->id());
    }

    public static function none(): self
    {
        return new self(null, null);
    }

    /**
     * The resource that was at the address, or null when none is known.
     */
    public function resource(): ?Resource
    {
        return null === $this->type || null === $this->id ? null : new Resource($this->type, $this->id);
    }
}
