<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Infrastructure\Event\EventRetry;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;

final class CmsPostSubscribersTest extends KernelTestCase
{
    public const string POST = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1b01';

    private const string OTHER_POST = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1b02';

    public function testItPutsOnTheSiteThePostTheCmsPublishes(): void
    {
        $body = self::body();

        $this->handle($body);

        self::assertEquals(Post::publish(CmsPostPublished::fromPrimitives(self::POST, $body + ['revision' => 1])), $this->storedPost());
    }

    public function testItReplacesThePostWithTheVersionTheCmsPublishesLater(): void
    {
        $this->handle(self::body());

        $this->handle(self::body(title: 'Otro título', sectionSlug: 'dia-a-dia', tagIds: []), 2);

        self::assertSame(['Otro título', 'dia-a-dia', []], [$this->storedPost()?->title(), $this->storedPost()?->sectionSlug(), $this->storedPost()?->tagIds()]);
    }

    public function testItKeepsThePostWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle(self::body(title: 'Lo último'), 2);

        $this->handle(self::body(title: 'Lo primero'));

        self::assertSame('Lo último', $this->storedPost()?->title());
    }

    public function testItWaitsForAnotherPostToFreeTheAddressBeforeTakingIt(): void
    {
        $this->handle(self::body(), id: self::OTHER_POST);

        try {
            $this->handle(self::body());
            self::fail('A post was put at the address of another one.');
        } catch (EventRetry $retry) {
            self::assertSame(60_000, $retry->getRetryDelay(), 'it waits a minute, for a cron run to take the event that frees the address');
            self::assertNull($this->storedPost());
        }

        $this->dispatch('cms.post.unpublished', self::OTHER_POST, ['revision' => 2]);
        $this->handle(self::body());

        self::assertSame('mi-primer-dia', $this->storedPost()?->slug());
    }

    public function testItTakesTheSlugAnotherPostHasOnAnotherDay(): void
    {
        $this->handle(self::body(day: '2026-11-06'), id: self::OTHER_POST);

        $this->handle(self::body());

        self::assertSame('mi-primer-dia', $this->storedPost()?->slug());
    }

    public function testItTakesOffTheSiteThePostTheCmsUnpublishes(): void
    {
        $this->handle(self::body());

        $this->dispatch('cms.post.unpublished', self::POST, ['revision' => 2]);

        self::assertNull($this->storedPost());
    }

    /**
     * A post already gone stays gone, so the same event can be taken twice.
     */
    public function testItTakesAnUnpublicationOfAPostNotOnTheSite(): void
    {
        $this->dispatch('cms.post.unpublished', self::POST, ['revision' => 2]);

        self::assertNull($this->storedPost());
    }

    /**
     * Whatever the body lacks of what the CMS sends, the message is refused.
     */
    #[DataProvider('malformedBodies')]
    public function testItRefusesAPublicationWhoseBodyTheCmsNeverSends(string $key, mixed $value): void
    {
        $body = self::body();
        $body[$key] = $value;

        try {
            $this->handle($body);
            self::fail('A publication the CMS never sends was taken.');
        } catch (\Throwable) {
            self::assertNull($this->storedPost());
        }
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'a title that is no text' => ['title', 3];
        yield 'tags that are no list' => ['tag_ids', 'etiqueta'];
        yield 'an unknown robots directive' => ['robots', 'noindex'];
        yield 'a date that is no date' => ['first_published_at', 'ayer'];
    }

    /**
     * What the CMS sends of a post filed under a section, as published then.
     *
     * @param list<string> $tagIds
     *
     * @return array<string, mixed>
     */
    public static function body(
        string $title = 'Mi primer día sin ruido',
        string $sectionSlug = 'vida-diaria',
        string $day = '2026-11-07',
        array $tagIds = ['01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a02'],
    ): array {
        return [
            'title' => $title,
            'slug' => 'mi-primer-dia',
            'section_id' => '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0a01',
            'section_name' => 'Vida diaria',
            'section_slug' => $sectionSlug,
            'author_id' => '01a1da50-7c41-7e02-9b6d-3f8a2c4e1b02',
            'author_name' => 'Isaac Abreu',
            'tag_ids' => $tagIds,
            'content' => ['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [['text' => 'Hoy probé los tapones.']]]]],
            'meta_description' => 'Qué cambió el primer día con tapones.',
            'robots' => 'index,follow',
            'day' => $day,
            'first_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body, int $revision = 1, string $id = self::POST): void
    {
        $body['revision'] = $revision;

        $this->dispatch('cms.post.published', $id, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatch(string $name, string $id, array $body): void
    {
        // As the worker does after each message, so the projection reads
        // what it keeps from the database, not from Doctrine's memory.
        self::getContainer()->get(EntityManagerInterface::class)->clear();
        self::getContainer()->get(DomainEventMessageHandler::class)(new DomainEventMessage($name, $id, $body));
    }

    private function storedPost(): ?Post
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(PostRepository::class)->find(new PostId(self::POST));
    }
}
