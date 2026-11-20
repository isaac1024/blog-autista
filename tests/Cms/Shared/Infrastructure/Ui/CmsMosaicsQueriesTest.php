<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Ui;

use Cms\Post\Domain\PostRepository;
use Cms\Shared\Infrastructure\Ui\CmsMosaics;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Post\Domain\PostMother;
use Cms\Tests\Taxonomy\Domain\TermMother;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\RichText\RichText;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The mosaics of a text previewed are read as on the site: each reads its
 * own posts, and their images and sections are read once for all, so four
 * mosaics ask the database only for their posts more than a single one.
 */
final class CmsMosaicsQueriesTest extends KernelTestCase
{
    public function testEachMosaicReadsOnlyItsPosts(): void
    {
        $sections = [];

        for ($i = 1; $i <= 4; ++$i) {
            $section = \sprintf('01a1f300-0000-7000-8000-%012d', $i);
            self::getContainer()->get(TermRepository::class)->save(TermMother::published(id: $section, name: 'Sección '.$i, slug: 'seccion-'.$i));

            for ($j = 1; $j <= 2; ++$j) {
                self::getContainer()->get(PostRepository::class)->save(PostMother::published(
                    id: \sprintf('01a1f000-0000-7000-8000-%06d%06d', $i, $j),
                    slug: \sprintf('entrada-%d-%d', $i, $j),
                    section: $section,
                    sectionSlug: 'seccion-'.$i,
                ));
            }

            $sections[] = $section;
        }

        $one = $this->queriesFor(self::text([$sections[0]]));
        $four = $this->queriesFor(self::text($sections));

        self::assertSame([[2], [2, 2, 2, 2]], [$one['cards'], $four['cards']]);
        self::assertSame($one['queries'] + 3 * $one['posts'], $four['queries']);
    }

    /**
     * How many cards each mosaic of the text shows, read anew; how many
     * queries reading them took, and how many of those read posts.
     *
     * @return array{cards: list<int>, queries: int, posts: int}
     */
    private function queriesFor(RichText $text): array
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();

        $mosaics = self::getContainer()->get(CmsMosaics::class)($text);
        $queries = $holder->getData()['default'] ?? [];

        return [
            'cards' => array_values(array_map(\count(...), $mosaics)),
            'queries' => \count($queries),
            'posts' => \count(array_filter($queries, static fn (array $query): bool => \is_string($query['sql']) && str_contains($query['sql'], 'cms_post'))),
        ];
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
