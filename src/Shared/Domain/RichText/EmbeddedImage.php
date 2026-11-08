<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * An image of the library, embedded by its id alone: whoever shows it reads
 * its texts, and whether it is on the site, where it is kept, so they are
 * always the latest.
 */
final readonly class EmbeddedImage implements Block
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private string $imageId)
    {
        if (1 !== preg_match(self::UUID, $imageId)) {
            throw InvalidRichText::malformed('image.id', 'the id of an image');
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data, string $path): self
    {
        $id = Parse::string($data['id'] ?? null, $path.'.id');

        if (1 !== preg_match(self::UUID, $id)) {
            throw InvalidRichText::malformed($path.'.id', 'the id of an image');
        }

        return new self($id);
    }

    public function imageId(): string
    {
        return $this->imageId;
    }

    #[\Override]
    public function type(): BlockType
    {
        return BlockType::Image;
    }

    #[\Override]
    public function texts(): array
    {
        return [];
    }

    #[\Override]
    public function toArray(): array
    {
        return ['type' => $this->type()->value, 'id' => $this->imageId];
    }
}
