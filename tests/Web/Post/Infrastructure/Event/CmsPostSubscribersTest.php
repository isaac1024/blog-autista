<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure\Event;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Event\DomainEventMessage;
use Shared\Infrastructure\Event\DomainEventMessageHandler;
use Shared\Infrastructure\Event\EventRetry;
use Shared\Tests\Infrastructure\Http\ForgottenPages;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostId;
use Web\Post\Domain\PostRepository;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;
use Web\Tests\Taxonomy\Domain\SectionSlugs;

final class CmsPostSubscribersTest extends KernelTestCase
{
    use SectionSlugs;

    private const string OTHER_POST = '01a1da50-3c8d-7e01-9b4f-6d3fac2e1b02';

    public function testItPutsOnTheSiteThePostTheCmsPublishes(): void
    {
        $body = CmsPostPublishedMother::body();

        $this->handle($body);

        self::assertEquals(Post::publish(CmsPostPublished::fromPrimitives(PostIdMother::DEFAULT_VALUE, $body + ['revision' => 1])), $this->storedPost());
    }

    public function testItReplacesThePostWithTheVersionTheCmsPublishesLater(): void
    {
        $this->handle(CmsPostPublishedMother::body());

        $this->handle(CmsPostPublishedMother::body(title: 'Otro título', tagIds: []), 2);

        self::assertSame(['Otro título', []], [$this->storedPost()?->title(), $this->storedPost()?->tagIds()]);
    }

    public function testItKeepsThePostWhenAnEarlierVersionArrivesLate(): void
    {
        $this->handle(CmsPostPublishedMother::body(title: 'Lo último'), 2);

        $this->handle(CmsPostPublishedMother::body(title: 'Lo primero'));

        self::assertSame('Lo último', $this->storedPost()?->title());
    }

    public function testItWaitsForAnotherPostToFreeTheAddressBeforeTakingIt(): void
    {
        $this->handle(CmsPostPublishedMother::body(), id: self::OTHER_POST);

        try {
            $this->handle(CmsPostPublishedMother::body());
            self::fail('A post was put at the address of another one.');
        } catch (EventRetry $retry) {
            self::assertSame(60_000, $retry->getRetryDelay(), 'it waits a minute, for a cron run to take the event that frees the address');
            self::assertNull($this->storedPost());
        }

        $this->dispatch('cms.post.unpublished', self::OTHER_POST, ['revision' => 2]);
        $this->handle(CmsPostPublishedMother::body());

        self::assertSame('mi-primer-dia', $this->storedPost()?->slug());
    }

    public function testItTakesTheSlugAnotherPostHasOnAnotherDay(): void
    {
        $this->handle(CmsPostPublishedMother::body(day: '2026-11-06'), id: self::OTHER_POST);

        $this->handle(CmsPostPublishedMother::body());

        self::assertSame('mi-primer-dia', $this->storedPost()?->slug());
    }

    public function testItTakesOffTheSiteThePostTheCmsUnpublishes(): void
    {
        $this->handle(CmsPostPublishedMother::body());

        $this->dispatch('cms.post.unpublished', PostIdMother::DEFAULT_VALUE, ['revision' => 2]);

        self::assertNull($this->storedPost());
    }

    /**
     * Taken off the site, or corrected, a post is forgotten by the page cache
     * where it was, and where it is once corrected.
     */
    public function testItHasThePageCacheForgetThePostTakenOffOrCorrected(): void
    {
        self::saveSectionSlug();
        $this->handle(CmsPostPublishedMother::body());
        ForgottenPages::take();

        $this->handle(['slug' => 'mi-primer-dia-corregido'] + CmsPostPublishedMother::body(), 2);
        $corrected = ForgottenPages::take();
        $this->dispatch('cms.post.unpublished', PostIdMother::DEFAULT_VALUE, ['revision' => 3]);
        $unpublished = ForgottenPages::take();

        self::assertCount(2, $corrected);
        self::assertStringEndsWith('/2026/11/07/mi-primer-dia.html', $corrected[0]);
        self::assertStringEndsWith('/2026/11/07/mi-primer-dia-corregido.html', $corrected[1]);
        self::assertSame([$corrected[1]], $unpublished);
    }

    /**
     * A post already gone stays gone, so the same event can be taken twice.
     */
    public function testItTakesAnUnpublicationOfAPostNotOnTheSite(): void
    {
        $this->dispatch('cms.post.unpublished', PostIdMother::DEFAULT_VALUE, ['revision' => 2]);

        self::assertNull($this->storedPost());
    }

    /**
     * Whatever the body lacks of what the CMS sends, the message is refused.
     */
    #[DataProvider('malformedBodies')]
    public function testItRefusesAPublicationWhoseBodyTheCmsNeverSends(string $key, mixed $value): void
    {
        $body = CmsPostPublishedMother::body();
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
        yield 'no opening image' => ['opening_image_id', null];
        yield 'an unknown robots directive' => ['robots', 'noindex'];
        yield 'a date that is no date' => ['first_published_at', 'ayer'];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body, int $revision = 1, string $id = PostIdMother::DEFAULT_VALUE): void
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

        return self::getContainer()->get(PostRepository::class)->find(new PostId(PostIdMother::DEFAULT_VALUE));
    }
}
