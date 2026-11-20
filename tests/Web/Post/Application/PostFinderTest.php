<?php

declare(strict_types=1);

namespace Web\Tests\Post\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\RichText\RichText;
use Shared\Tests\Domain\ClockMother;
use Web\Post\Application\PostFinder;
use Web\Post\Application\PostNotFound;
use Web\Post\Application\PostResponse;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Shared\Domain\Robots;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

final class PostFinderTest extends TestCase
{
    public function testItHandsOverThePostAtTheAddress(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())
            ->method('findAt')
            ->with(self::equalTo(new \DateTimeImmutable('2026-11-07')), 'mi-primer-dia')
            ->willReturn(Post::publish(CmsPostPublishedMother::create(robots: Robots::NoindexFollow)));

        self::assertEquals(
            new PostResponse(
                PostIdMother::DEFAULT_VALUE,
                'Mi primer día sin ruido',
                'mi-primer-dia',
                CmsPostPublishedMother::SECTION,
                'Vida diaria',
                'vida-diaria',
                CmsPostPublishedMother::AUTHOR,
                'Isaac Abreu',
                [CmsPostPublishedMother::TAG],
                CmsPostPublishedMother::OPENING_IMAGE,
                RichText::fromArray(CmsPostPublishedMother::CONTENT),
                'Qué cambió el primer día con tapones.',
                1,
                'noindex,follow',
                '2026-11-07',
                ClockMother::now(),
                ClockMother::now(),
            ),
            (new PostFinder('2026-11-07', 'mi-primer-dia'))($repository),
        );
    }

    /**
     * How long it takes to read, at 200 words a minute rounded up, as when
     * it was published: a minute at least, whatever little it says.
     */
    public function testItHandsOverTheMinutesThePostTakesToRead(): void
    {
        foreach ([400 => 2, 401 => 3, 0 => 1] as $words => $minutes) {
            $content = ['version' => 1, 'blocks' => 0 === $words ? [] : [
                ['type' => 'heading', 'level' => 2, 'text' => [['text' => 'Ruido']]],
                ['type' => 'paragraph', 'text' => [['text' => trim(str_repeat('día, ', $words - 1))]]],
            ]];
            $repository = self::createStub(PostRepository::class);
            $repository->method('findAt')->willReturn(Post::publish(CmsPostPublishedMother::create(content: $content)));

            self::assertSame(
                $minutes,
                (new PostFinder('2026-11-07', 'mi-primer-dia'))($repository)->readingMinutes,
                \sprintf('%d words', $words),
            );
        }
    }

    public function testItThrowsWhenNoPostIsAtTheAddress(): void
    {
        $repository = self::createStub(PostRepository::class);
        $repository->method('findAt')->willReturn(null);

        $this->expectExceptionObject(PostNotFound::atAddress('2026-11-07/mi-primer-dia'));

        (new PostFinder('2026-11-07', 'mi-primer-dia'))($repository);
    }

    /**
     * A day that is no day, or one that rolls over into another, is no
     * address of a post, and nothing is looked up for it.
     */
    #[DataProvider('daysThatAreNone')]
    public function testItThrowsWithoutLookingWhenTheDayIsNone(string $day): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::never())->method('findAt');

        $this->expectExceptionObject(PostNotFound::atAddress(\sprintf('%s/mi-primer-dia', $day)));

        (new PostFinder($day, 'mi-primer-dia'))($repository);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function daysThatAreNone(): iterable
    {
        yield 'a day past the end of its month' => ['2026-02-30'];
        yield 'no day at all' => ['ayer'];
    }
}
