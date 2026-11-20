<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure\Resolution;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\RichText\RichText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Shared\Infrastructure\Resolution\SiteLinkedContents;
use Web\Taxonomy\Domain\Taxonomy;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Author\Domain\CmsAuthorPublishedMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Taxonomy\Domain\CmsTermPublishedMother;
use Web\Tests\Taxonomy\Domain\SectionSlugs;

/**
 * What a text links to is read a kind at a time: a text linking to five
 * posts, five tags and two authors asks the database as many times as one
 * linking to one of each.
 */
final class SiteLinkedContentsQueriesTest extends KernelTestCase
{
    use SectionSlugs;

    #[\Override]
    protected function setUp(): void
    {
        self::saveSectionSlug();
    }

    public function testItReadsWhatATextLinksToAKindAtATime(): void
    {
        $postsAndTags = 5;
        $authors = 2;
        $ids = $this->publish($postsAndTags, $authors);

        $one = $this->queriesFor(self::text([$ids['posts'][0]], [$ids['tags'][0]], [$ids['authors'][0]]));
        $many = $this->queriesFor(self::text($ids['posts'], $ids['tags'], $ids['authors']));

        self::assertSame([$one['linked'], $one['queries']], [3, $many['queries']]);
        self::assertSame($postsAndTags * 2 + $authors, $many['linked']);
    }

    /**
     * @return array{posts: list<string>, tags: list<string>, authors: list<string>}
     */
    private function publish(int $postsAndTags, int $authors): array
    {
        $ids = ['posts' => [], 'tags' => [], 'authors' => []];

        for ($i = 1; $i <= $postsAndTags; ++$i) {
            $post = \sprintf('01a1f000-0000-7000-8000-%012d', $i);
            $tag = \sprintf('01a1f100-0000-7000-8000-%012d', $i);
            self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublishedMother::create(id: $post, slug: 'post-'.$i)));
            self::getContainer()->get(TermRepository::class)->save(Term::publish(CmsTermPublishedMother::create(id: $tag, taxonomy: Taxonomy::Tag, name: 'Etiqueta '.$i, slug: 'etiqueta-'.$i)));
            $ids['posts'][] = $post;
            $ids['tags'][] = $tag;
        }

        for ($i = 1; $i <= $authors; ++$i) {
            $author = \sprintf('01a1f200-0000-7000-8000-%012d', $i);
            self::getContainer()->get(AuthorRepository::class)->save(Author::publish(CmsAuthorPublishedMother::create(id: $author, name: 'Autor '.$i, slug: 'autor-'.$i)));
            $ids['authors'][] = $author;
        }

        return $ids;
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

        $linked = self::getContainer()->get(SiteLinkedContents::class)($text, [], null);

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
