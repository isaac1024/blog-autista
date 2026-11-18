<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Resolution;

use Cms\Author\Domain\AuthorRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Shared\Infrastructure\Resolution\CmsLinkedContents;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\RichText\RichText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What a text previewed links to is read a kind at a time, as on the site:
 * a text linking to five posts, five tags and two authors asks the database
 * as many times as one linking to one of each.
 */
final class CmsLinkedContentsQueriesTest extends KernelTestCase
{
    public function testItReadsWhatATextLinksToAKindAtATime(): void
    {
        self::getContainer()->get(TermRepository::class)->save(TermMother::published(id: PostMother::SECTION, slug: 'vida-diaria'));
        $ids = ['posts' => [], 'tags' => [], 'authors' => []];

        for ($i = 1; $i <= 5; ++$i) {
            $post = \sprintf('01a1f000-0000-7000-8000-%012d', $i);
            $tag = \sprintf('01a1f100-0000-7000-8000-%012d', $i);
            self::getContainer()->get(PostRepository::class)->save(PostMother::published(id: $post, slug: 'post-'.$i));
            self::getContainer()->get(TermRepository::class)->save(TermMother::published(id: $tag, taxonomy: Taxonomy::Tag, name: 'Etiqueta '.$i, slug: 'etiqueta-'.$i));
            $ids['posts'][] = $post;
            $ids['tags'][] = $tag;
        }

        for ($i = 1; $i <= 2; ++$i) {
            $author = \sprintf('01a1f200-0000-7000-8000-%012d', $i);
            self::getContainer()->get(AuthorRepository::class)->save(AuthorMother::published(id: $author, name: 'Autor '.$i, slug: 'autor-'.$i));
            $ids['authors'][] = $author;
        }

        $one = $this->queriesFor(self::text([$ids['posts'][0]], [$ids['tags'][0]], [$ids['authors'][0]]));
        $many = $this->queriesFor(self::text($ids['posts'], $ids['tags'], $ids['authors']));

        self::assertSame([3, $one['queries']], [$one['linked'], $many['queries']]);
        self::assertSame(12, $many['linked']);
    }

    /**
     * How many contents the text links to, read anew, and how many queries
     * reading them took.
     *
     * @return array{linked: int, queries: int}
     */
    private function queriesFor(RichText $text): array
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();

        $linked = self::getContainer()->get(CmsLinkedContents::class)($text, [], null);

        return ['linked' => \count($linked), 'queries' => \count($holder->getData()['default'] ?? [])];
    }

    /**
     * @param list<string> $posts
     * @param list<string> $tags
     * @param list<string> $authors
     */
    private static function text(array $posts, array $tags, array $authors): RichText
    {
        $link = static fn (string $type): \Closure => static fn (string $id): array => ['text' => 'enlace', 'marks' => ['link'], $type.'_id' => $id];

        return RichText::fromArray(['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [
            ...array_map($link('post'), $posts),
            ...array_map($link('term'), $tags),
            ...array_map($link('author'), $authors),
        ]]]]);
    }
}
