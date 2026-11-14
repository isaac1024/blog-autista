<?php

declare(strict_types=1);

namespace Cms\Post\Domain;

use Doctrine\ORM\Mapping as ORM;

/**
 * Where a post is filed and who signs it: the section it is under, its author
 * and its tags. A post may be saved without a section or an author, and
 * needs both to be published; it may have no tag at all.
 */
#[ORM\Embeddable]
final readonly class Classification
{
    public function __construct(
        #[ORM\Column(name: 'section_id', type: 'cms_post_section_id', nullable: true)]
        private ?SectionId $section,
        #[ORM\Column(name: 'author_id', type: 'cms_post_author_id', nullable: true)]
        private ?AuthorId $author,
        #[ORM\Column(name: 'tag_ids', type: 'cms_post_tag_ids')]
        private TagIds $tags,
    ) {}

    public static function none(): self
    {
        return new self(null, null, new TagIds());
    }

    /**
     * From what an editor picks, blank for no section or no author.
     *
     * @param list<string> $tags
     */
    public static function fromPrimitives(string $section, string $author, array $tags): self
    {
        return new self(
            '' === $section ? null : new SectionId($section),
            '' === $author ? null : new AuthorId($author),
            TagIds::fromValues($tags),
        );
    }

    /**
     * Checks that its section is a section, its author an author and each of
     * its tags a tag, as the CMS has them now, on the site or not.
     */
    public function assertKnownTo(Classifiers $classifiers): void
    {
        if (null !== $this->section && !$classifiers->isSection($this->section)) {
            throw InvalidClassification::unknownSection($this->section);
        }

        if (null !== $this->author && !$classifiers->isAuthor($this->author)) {
            throw InvalidClassification::unknownAuthor($this->author);
        }

        foreach ($this->tags->ids() as $tag) {
            if (!$classifiers->isTag($tag)) {
                throw InvalidClassification::unknownTag($tag);
            }
        }
    }

    public function equals(self $other): bool
    {
        return $this->section?->value() === $other->section?->value()
            && $this->author?->value() === $other->author?->value()
            && $this->tags->equals($other->tags);
    }

    public function section(): ?SectionId
    {
        return $this->section;
    }

    public function author(): ?AuthorId
    {
        return $this->author;
    }

    public function tags(): TagIds
    {
        return $this->tags;
    }
}
