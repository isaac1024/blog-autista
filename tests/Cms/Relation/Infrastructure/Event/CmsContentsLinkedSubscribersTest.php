<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Event;

use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What pages, bios, captions and posts link to, as each tells it, kept in
 * use: the posts, sections and tags, and authors of each, as told last.
 */
final class CmsContentsLinkedSubscribersTest extends KernelTestCase
{
    private const string SOURCE = '01a1b801-ce58-723d-8234-b76d9105f901';
    private const string POST = '01a1b801-ce58-723d-8234-b76d9105f911';
    private const string OTHER_POST = '01a1b801-ce58-723d-8234-b76d9105f912';
    private const string TERM = '01a1b801-ce58-723d-8234-b76d9105f921';
    private const string AUTHOR = '01a1b801-ce58-723d-8234-b76d9105f931';

    /**
     * @return iterable<string, array{string}>
     */
    public static function sources(): iterable
    {
        yield 'a page' => ['cms.page.contents_linked'];
        yield 'an author' => ['cms.author.contents_linked'];
        yield 'an image' => ['cms.image.contents_linked'];
    }

    #[DataProvider('sources')]
    public function testItKeepsInUseWhatTheSourceLinksToAsItTellsItLast(string $event): void
    {
        $this->handle($event, ['post_ids' => [self::POST], 'term_ids' => [self::TERM], 'author_ids' => [self::AUTHOR]]);
        $this->handle($event, ['post_ids' => [self::OTHER_POST], 'term_ids' => [self::TERM], 'author_ids' => []]);

        self::assertSame(
            [false, true, true, false],
            [
                $this->repository()->isUsed(ResourceType::Post, self::POST),
                $this->repository()->isUsed(ResourceType::Post, self::OTHER_POST),
                $this->repository()->isUsed(ResourceType::Term, self::TERM),
                $this->repository()->isUsed(ResourceType::Author, self::AUTHOR),
            ],
        );
    }

    /**
     * What a source links to is told apart from what else it uses, which
     * it leaves be, such as the pages it links to.
     */
    public function testItLeavesThePagesTheSourceLinksToBe(): void
    {
        $this->repository()->replace(ResourceType::Page, self::SOURCE, ResourceType::Page, [self::OTHER_POST]);

        $this->handle('cms.page.contents_linked', ['post_ids' => [], 'term_ids' => [], 'author_ids' => []]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Page, self::OTHER_POST));
    }

    public function testItKeepsInUseThePostsAPostLinksTo(): void
    {
        $this->handle('cms.post.posts_linked', ['post_ids' => [self::POST]]);

        self::assertTrue($this->repository()->isUsed(ResourceType::Post, self::POST));
    }

    /**
     * A body the CMS never sends is refused, and relates nothing.
     */
    public function testItRefusesWhatIsNotAListOfIds(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        $this->handle('cms.page.contents_linked', ['post_ids' => [5], 'term_ids' => [], 'author_ids' => []]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(string $event, array $body): void
    {
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage($event, self::SOURCE, $body));
    }

    private function repository(): RelationRepository
    {
        return self::getContainer()->get(RelationRepository::class);
    }
}
