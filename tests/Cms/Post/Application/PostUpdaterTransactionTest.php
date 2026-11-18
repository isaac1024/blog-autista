<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Application;

use Cms\Post\Application\PostUpdater;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\PostId;
use Cms\Post\Domain\PostImages;
use Cms\Post\Domain\PostRepository;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Shared\Tests\Domain\RichText\RichTextMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What a post uses is told at once, to subscribers that write it down within
 * the same change: when telling fails, neither the post edited nor what it
 * uses is kept.
 */
final class PostUpdaterTransactionTest extends KernelTestCase
{
    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105d002';
    private const string TITLE = 'Un título nuevo';

    #[\Override]
    protected function setUp(): void
    {
        self::getContainer()->get(PostRepository::class)->save(PostMother::create());
    }

    public function testItKeepsThePostEditedAndWhatItUsesTogether(): void
    {
        $this->update(self::getContainer()->get(EventBus::class));

        self::assertSame([self::TITLE, true], [$this->savedTitle(), $this->isPageUsed()]);
    }

    public function testItKeepsNeitherThePostEditedNorWhatItUsesWhenTellingFails(): void
    {
        $depth = self::getContainer()->get(Connection::class)->getTransactionNestingLevel();
        $failure = new \RuntimeException('A subscriber failed after the others wrote down what they were told.');
        $thrown = null;

        try {
            $this->update(self::failingAfterTelling(self::getContainer()->get(EventBus::class), $failure));
        } catch (\RuntimeException $exception) {
            $thrown = $exception;
        }

        self::assertSame(
            [$failure, PostMother::TITLE, false, $depth],
            [$thrown, $this->savedTitle(), $this->isPageUsed(), self::getContainer()->get(Connection::class)->getTransactionNestingLevel()],
        );
    }

    private function update(EventBus $eventBus): void
    {
        new PostUpdater(
            PostIdMother::DEFAULT_VALUE,
            self::TITLE,
            PostMother::SLUG,
            '',
            '',
            [],
            '',
            RichTextMother::stored(['type' => 'paragraph', 'text' => [['text' => 'Más', 'marks' => ['link'], 'page_id' => self::PAGE]]]),
            '',
            'index,follow',
        )(
            self::getContainer()->get(PostRepository::class),
            $eventBus,
            self::getContainer()->get(Transaction::class),
            self::getContainer()->get(Classifiers::class),
            self::getContainer()->get(PostImages::class),
        );
    }

    /**
     * Tells every event to its subscribers, then fails, as the last of them
     * would.
     */
    private static function failingAfterTelling(EventBus $eventBus, \RuntimeException $failure): EventBus
    {
        return new readonly class($eventBus, $failure) implements EventBus {
            public function __construct(private EventBus $eventBus, private \RuntimeException $failure) {}

            #[\Override]
            public function publish(DomainEvent ...$events): void
            {
                $this->eventBus->publish(...$events);

                throw $this->failure;
            }
        };
    }

    private function savedTitle(): ?string
    {
        // Read again from the database, not from what was loaded.
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PostRepository::class)->find(new PostId(PostIdMother::DEFAULT_VALUE))?->title()->value();
    }

    private function isPageUsed(): bool
    {
        return self::getContainer()->get(RelationRepository::class)->isUsed(ResourceType::Page, self::PAGE);
    }
}
