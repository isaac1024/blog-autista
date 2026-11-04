<?php

declare(strict_types=1);

namespace Shared\Domain\RichText;

/**
 * A top-level piece of a rich text document.
 */
interface Block
{
    public function type(): BlockType;

    /**
     * Every piece of text in the block, so rules on marks reach all of them.
     *
     * @return list<InlineText>
     */
    public function texts(): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
