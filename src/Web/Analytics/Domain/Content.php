<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * What a visit was to: a page, a post, a section or a tag, or an author, by
 * its kind and its id.
 */
final readonly class Content
{
    public function __construct(
        private ContentType $type,
        private ContentId $id,
    ) {}

    /**
     * @throws InvalidVisit when the type is no kind of content whose visits are counted
     */
    public static function fromPrimitives(string $type, string $id): self
    {
        return new self(
            ContentType::tryFrom($type) ?? throw InvalidVisit::unknownContentType($type),
            new ContentId($id),
        );
    }

    public function type(): ContentType
    {
        return $this->type;
    }

    public function id(): ContentId
    {
        return $this->id;
    }
}
