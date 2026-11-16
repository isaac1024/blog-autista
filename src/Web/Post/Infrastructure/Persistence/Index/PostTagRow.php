<?php

declare(strict_types=1);

namespace Web\Post\Infrastructure\Persistence\Index;

use Doctrine\ORM\Mapping as ORM;
use Web\Post\Domain\PostId;

/**
 * A row of the index of the posts of each tag, which the repository keeps
 * with every post it saves: the tag, the post, and the day the post was
 * first published, so the posts of a tag are listed newest first through
 * the index alone, never by reading the tags each post keeps. The post keeps
 * its tags, in the order the editor gave them, on its own.
 *
 * It is mapped only so the schema has it; the repository reads and writes
 * it in plain SQL.
 */
#[ORM\Entity]
#[ORM\Table(name: 'web_post_tag')]
#[ORM\Index(name: 'idx_web_post_tag_listing', columns: ['term_id', 'first_published_at', 'post_id'])]
final readonly class PostTagRow
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'web_post_id')]
        public PostId $postId,
        #[ORM\Id]
        #[ORM\Column(type: 'guid')]
        public string $termId,
        #[ORM\Column]
        public \DateTimeImmutable $firstPublishedAt,
    ) {}
}
