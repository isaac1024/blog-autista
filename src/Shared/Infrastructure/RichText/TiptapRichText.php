<?php

declare(strict_types=1);

namespace Shared\Infrastructure\RichText;

use Shared\Domain\RichText\InvalidRichText;

/**
 * Translates between the documents of the Tiptap editor (ProseMirror JSON)
 * and the stored rich text format, so nothing past this adapter knows Tiptap.
 * Each way has a class of its own; reading is the strict one.
 */
final readonly class TiptapRichText
{
    /**
     * @throws InvalidRichText when the document is not one the editor can produce
     *
     * @return array{version: int, blocks: list<array<string, mixed>>} the stored format
     */
    public function toStored(string $json): array
    {
        return new TiptapDocumentReader()->read($json);
    }

    /**
     * @param array<array-key, mixed> $stored the stored format
     *
     * @return array<string, mixed> a Tiptap document
     */
    public function toTiptap(array $stored): array
    {
        return new TiptapDocumentWriter()->write($stored);
    }
}
