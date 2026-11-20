<?php

declare(strict_types=1);

namespace Web\Taxonomy\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * The slug of a section, the one it had when the CMS last published it,
 * kept while the section is off the site too: the address of each of its
 * posts names it, published or not. A section is never deleted while posts
 * are filed under it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_section_slug')]
final class SectionSlug
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        private readonly string $sectionId,
        #[ORM\Column(length: 80)]
        private string $slug,
    ) {}

    /**
     * The slug of the section the CMS published, in place of the one it had.
     */
    public static function published(CmsTermPublished $event, ?self $kept): self
    {
        if (null === $kept) {
            return new self($event->aggregateId(), $event->slug());
        }

        $kept->slug = $event->slug();

        return $kept;
    }
}
