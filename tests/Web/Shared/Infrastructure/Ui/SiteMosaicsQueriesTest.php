<?php

declare(strict_types=1);

namespace Web\Tests\Shared\Infrastructure\Ui;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\RichText\RichText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Shared\Infrastructure\Ui\SiteMosaics;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;

/**
 * The mosaics of a text are read at once but for which posts each shows:
 * four mosaics ask the database one more time each than a single one, for
 * those posts, and read the cards, their images and their sections once.
 */
final class SiteMosaicsQueriesTest extends KernelTestCase
{
    public function testEachMosaicReadsOnlyWhichPostsItShows(): void
    {
        $sections = [];

        for ($i = 1; $i <= 4; ++$i) {
            $section = \sprintf('01a1f300-0000-7000-8000-%012d', $i);
            self::getContainer()->get(TermRepository::class)->save(Term::publish(CmsTermPublished::fromPrimitives($section, [
                'taxonomy' => 'section',
                'name' => 'Sección '.$i,
                'slug' => 'seccion-'.$i,
                'meta_description' => 'Sobre esto.',
                'robots' => 'index,follow',
                'first_published_at' => '2026-11-07T10:00:00+00:00',
                'last_published_at' => '2026-11-07T10:00:00+00:00',
                'revision' => 1,
            ])));

            for ($j = 1; $j <= 2; ++$j) {
                self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublished::fromPrimitives(\sprintf('01a1f000-0000-7000-8000-%06d%06d', $i, $j), [
                    'title' => \sprintf('Entrada %d.%d', $i, $j),
                    'slug' => \sprintf('entrada-%d-%d', $i, $j),
                    'section_id' => $section,
                    'section_slug' => 'seccion-'.$i,
                    'revision' => 1,
                ] + CmsPostPublishedMother::body())));
            }

            $sections[] = $section;
        }

        $one = $this->queriesFor(self::text([$sections[0]]));
        $four = $this->queriesFor(self::text($sections));

        self::assertSame([[2], [2, 2, 2, 2]], [$one['cards'], $four['cards']]);
        self::assertSame($one['queries'] + 3, $four['queries']);
    }

    /**
     * How many cards each mosaic of the text shows, read anew, and how many
     * queries reading them took.
     *
     * @return array{cards: list<int>, queries: int}
     */
    private function queriesFor(RichText $text): array
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();

        $mosaics = self::getContainer()->get(SiteMosaics::class)($text);

        return ['cards' => array_values(array_map(\count(...), $mosaics)), 'queries' => \count($holder->getData()['default'] ?? [])];
    }

    /**
     * A text of one mosaic of the latest two posts of each section given.
     *
     * @param list<string> $sections
     */
    private static function text(array $sections): RichText
    {
        return RichText::fromArray(['version' => 1, 'blocks' => array_map(
            static fn (string $section): array => ['type' => 'mosaic', 'limit' => 2, 'section_id' => $section, 'author_id' => null, 'tag_ids' => []],
            $sections,
        )]);
    }
}
