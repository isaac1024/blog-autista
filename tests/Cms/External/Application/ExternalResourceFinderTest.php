<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\ExternalResourceFinder;
use Cms\External\Application\ExternalResourceNotFound;
use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ExternalResourceId;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\External\Domain\ResourceStatus;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\External\ResourceKind;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;

final class ExternalResourceFinderTest extends TestCase
{
    public function testItFindsTheResourceAsTheCmsShowsIt(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(new ExternalResourceId(ExternalResourceMother::ID)))
            ->willReturn(ExternalResourceMother::video());

        self::assertEquals(
            new ExternalResourceResponse(
                ExternalResourceMother::ID,
                'video',
                'youtube',
                ExternalResourceMother::VIDEO_URL,
                'Un vídeo',
                null,
                'es',
                [],
                ExternalResourceMother::IMAGE,
                'landscape',
                'draft',
                false,
                true,
                null,
                ExternalResourceMother::VIDEO_URL,
                ClockMother::now(),
                ClockMother::now(),
                true,
                false,
                true,
            ),
            new ExternalResourceFinder(ExternalResourceMother::ID)($repository),
        );
    }

    /**
     * @param array<string, mixed> $shown what the CMS shows of the resource
     */
    #[DataProvider('resources')]
    public function testItShowsWhatEachResourceIsAndWhetherItHasChangesToPublish(ExternalResource $resource, array $shown): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn($resource);

        $found = new ExternalResourceFinder(ExternalResourceMother::ID)($repository);

        $actual = array_intersect_key(get_object_vars($found), $shown);
        ksort($actual);
        ksort($shown);

        self::assertSame($shown, $actual);
    }

    /**
     * @return iterable<string, array{ExternalResource, array<string, mixed>}>
     */
    public static function resources(): iterable
    {
        $postOnSite = ExternalResourceMother::version(ResourceKind::SocialPost, ExternalResourceMother::POST_URL, ExternalResourceMother::POST, 'Publicación de Bluesky en Bluesky', ['text' => 'Antes'] + ExternalResourceMother::POST_DETAILS, null);
        $videoOnSite = ExternalResourceMother::version(ResourceKind::Video, ExternalResourceMother::VIDEO_URL, ExternalResourceMother::VIDEO, 'Antes', [], ExternalResourceMother::IMAGE);

        yield 'a short of YouTube, upright' => [ExternalResourceMother::video(url: 'https://www.youtube.com/shorts/dQw4w9WgXcQ', locator: 'shorts/dQw4w9WgXcQ'), ['imageShape' => 'portrait']];
        yield 'a post with no day' => [ExternalResourceMother::post(details: ['posted_on' => null] + ExternalResourceMother::POST_DETAILS), ['imageShape' => null, 'details' => ['author' => 'Bluesky', 'handle' => '@bsky.app', 'posted_on' => null, 'text' => "Hola.\n\nAdiós.", 'with_media' => false]]];
        yield 'a post on the site whose text changed' => [ExternalResourceMother::resource(ExternalResourceMother::ID, ResourceKind::SocialPost, 'bluesky', ExternalResourceMother::version(ResourceKind::SocialPost, ExternalResourceMother::POST_URL, ExternalResourceMother::POST, 'Publicación de Bluesky en Bluesky', ExternalResourceMother::POST_DETAILS, null), ResourceStatus::Published, $postOnSite, Availability::Available), ['changed' => true, 'hasSomethingToPublish' => true]];
        yield 'a resource taken off the site after it changed' => [ExternalResourceMother::video(status: ResourceStatus::Unpublished, published: $videoOnSite), ['status' => 'unpublished', 'changed' => false, 'hasSomethingToPublish' => true, 'canBeUnpublished' => false, 'canBeDeleted' => true]];
        yield 'a resource on the site as it is' => [ExternalResourceMother::video(status: ResourceStatus::Published), ['status' => 'published', 'changed' => false, 'hasSomethingToPublish' => false, 'canBeUnpublished' => true, 'canBeDeleted' => false]];
    }

    public function testItThrowsWhenTheResourceDoesNotExist(): void
    {
        $repository = self::createStub(ExternalResourceRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectExceptionObject(ExternalResourceNotFound::withId(ExternalResourceMother::ID));

        new ExternalResourceFinder(ExternalResourceMother::ID)($repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectExceptionObject(InvalidUuid::withValue('video'));

        new ExternalResourceFinder('video')($repository);
    }
}
