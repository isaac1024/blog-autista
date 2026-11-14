<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * What a rich text links to, or a widget shows: a content of the blog by its
 * kind and its id alone, so whoever shows it reads its name, and whether it
 * is on the site, where it is kept, and they are always the latest.
 */
final readonly class Link
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * @throws InvalidRichText when the id is no id of a content
     */
    public function __construct(
        private LinkType $type,
        private string $id,
        string $path,
    ) {
        if (1 !== preg_match(self::UUID, $id)) {
            throw InvalidRichText::malformed($path.'.'.$type->key(), \sprintf('the id of a %s', $type->value));
        }
    }

    /**
     * The link kept under the keys of the data, or null when it keeps none:
     * at most one, of any kind.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidRichText when it keeps more than one, or one that isn't an id
     */
    public static function fromArray(array $data, string $path): ?self
    {
        $links = [];

        foreach (LinkType::cases() as $type) {
            if (null !== ($data[$type->key()] ?? null)) {
                $links[] = new self($type, Parse::string($data[$type->key()], $path.'.'.$type->key()), $path);
            }
        }

        if (\count($links) > 1) {
            throw InvalidRichText::malformed($path, 'a link to one content at most');
        }

        return $links[0] ?? null;
    }

    public function type(): LinkType
    {
        return $this->type;
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * The id of what it links to when it is of the kind, null otherwise.
     */
    public function idOf(LinkType $type): ?string
    {
        return $type === $this->type ? $this->id : null;
    }

    /**
     * @return array<string, string> the id under the key of its kind
     */
    public function toArray(): array
    {
        return [$this->type->key() => $this->id];
    }
}
