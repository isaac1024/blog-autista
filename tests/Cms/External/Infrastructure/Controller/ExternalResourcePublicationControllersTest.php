<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Controller;

use Cms\External\Domain\Availability;
use Cms\External\Domain\ExternalResource;
use Cms\External\Domain\ResourceStatus;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\External\ResourceKind;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publishing, unpublishing, discarding and deleting an external resource
 * from its editor, which it goes back to, but deleting, which goes to the
 * list. A resource is published only with all its card or its facade shows.
 */
final class ExternalResourcePublicationControllersTest extends ExternalResourceControllerTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    #[DataProvider('actions')]
    public function testItRespondsNotFoundWhenTheResourceDoesNotExist(string $route, string $action): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, $route, self::HOST.'/externals/'.ExternalResourceMother::ID.'/'.$action);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function actions(): iterable
    {
        yield 'publishing' => ['cms_externals_publish', 'publish'];
        yield 'unpublishing' => ['cms_externals_unpublish', 'unpublish'];
        yield 'discarding' => ['cms_externals_discard', 'discard'];
        yield 'deleting' => ['cms_externals_delete', 'delete'];
        yield 'filling' => ['cms_externals_fill', 'fill'];
        yield 'checking' => ['cms_externals_check', 'check'];
    }

    public function testItPublishesTheResourceAndTellsTheSiteAllItPaints(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video());

        $this->post($client, 'cms_externals_publish', self::HOST.'/externals/'.ExternalResourceMother::ID.'/publish');

        self::assertResponseRedirects('/externals/'.ExternalResourceMother::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.external.published', 'parameters' => []]], self::successNotices($client));
        self::assertSame([['cms.external.published', ExternalResourceMother::ID, [
            'kind' => 'video',
            'provider' => 'youtube',
            'locator' => ExternalResourceMother::VIDEO,
            'url' => ExternalResourceMother::VIDEO_URL,
            'title' => 'Un vídeo',
            'summary' => null,
            'language' => 'es',
            'details' => [],
            'image' => ExternalResourceMother::IMAGE,
            'image_shape' => 'landscape',
            'available' => true,
            'revision' => 1,
        ]]], array_map(static fn ($event): array => [$event->name, $event->aggregateId, $event->body], $this->queuedEvents()));
        $stored = $this->storedResource(ExternalResourceMother::ID);
        self::assertSame([ResourceStatus::Published, false], [$stored?->status(), $stored?->hasSomethingToPublish()]);
    }

    #[DataProvider('unpublishable')]
    public function testItGoesBackAndTellsWhatTheResourceLacksToBePublished(ExternalResource $resource, string $key): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource($resource);

        $this->post($client, 'cms_externals_publish', self::HOST.'/externals/'.ExternalResourceMother::ID.'/publish');

        self::assertResponseRedirects('/externals/'.ExternalResourceMother::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => $key, 'parameters' => []]], self::errorNotices($client));
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{ExternalResource, string}>
     */
    public static function unpublishable(): iterable
    {
        yield 'a video without its image' => [ExternalResourceMother::video(image: null), 'errors.external.publication.without_image'];
        yield 'a post without who wrote it' => [ExternalResourceMother::post(details: ['author' => null] + ExternalResourceMother::POST_DETAILS), 'errors.external.publication.without_author'];
        yield 'a post without its account' => [ExternalResourceMother::post(details: ['handle' => null] + ExternalResourceMother::POST_DETAILS), 'errors.external.publication.without_author'];
        yield 'a post without its day' => [ExternalResourceMother::post(details: ['posted_on' => null] + ExternalResourceMother::POST_DETAILS), 'errors.external.publication.without_date'];
        yield 'a post without its text' => [ExternalResourceMother::post(details: ['text' => null] + ExternalResourceMother::POST_DETAILS), 'errors.external.publication.without_text'];
        yield 'a post of Bluesky by the handle of its account' => [ExternalResourceMother::post(url: 'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l', locator: 'bsky.app/3l6oveex3ii2l'), 'errors.external.publication.not_embeddable'];
        yield 'a study without its authors' => [ExternalResourceMother::study(details: ['authors' => null] + ExternalResourceMother::STUDY_DETAILS), 'errors.external.publication.without_authors'];
        yield 'a study without its journal' => [ExternalResourceMother::study(details: ['journal' => null] + ExternalResourceMother::STUDY_DETAILS), 'errors.external.publication.without_journal'];
        yield 'a study without its year' => [ExternalResourceMother::study(details: ['year' => null] + ExternalResourceMother::STUDY_DETAILS), 'errors.external.publication.without_year'];
        yield 'a resource published as it is' => [ExternalResourceMother::article(status: ResourceStatus::Published), 'errors.external.publication.nothing_to_publish'];
    }

    /**
     * TikTok tells no image of its videos, so they go without one.
     */
    public function testItPublishesAVideoOfAProviderThatTellsNoImage(): void
    {
        $client = self::createAuthenticatedClient();
        $version = ExternalResourceMother::version(ResourceKind::Video, 'https://www.tiktok.com/@scout2015/video/6718335390845095173', 'scout2015/6718335390845095173', 'Un vídeo de TikTok', [], null);
        $this->saveResource(ExternalResourceMother::resource(ExternalResourceMother::ID, ResourceKind::Video, 'tiktok', $version, ResourceStatus::Draft, null, Availability::Available));

        $this->post($client, 'cms_externals_publish', self::HOST.'/externals/'.ExternalResourceMother::ID.'/publish');

        self::assertSame([['key' => 'cms.notices.external.published', 'parameters' => []]], self::successNotices($client));
        self::assertSame([null, null], [$this->queuedEvents()[0]->body['image'], $this->queuedEvents()[0]->body['image_shape']]);
    }

    /**
     * A new address on the site is available until it is checked; the same
     * keeps what checking it found.
     */
    public function testItTakesANewAddressOnTheSiteForAvailable(): void
    {
        $client = self::createAuthenticatedClient();
        $onSite = ExternalResourceMother::version(ResourceKind::Article, 'https://www.example.org/antes.html', null, 'Una noticia', [], null);
        $this->saveResource(ExternalResourceMother::resource(ExternalResourceMother::ID, ResourceKind::Article, null, ExternalResourceMother::article()->version(), ResourceStatus::Published, $onSite, Availability::Unavailable));

        $this->post($client, 'cms_externals_publish', self::HOST.'/externals/'.ExternalResourceMother::ID.'/publish');

        self::assertSame([true, 2], [$this->queuedEvents()[0]->body['available'], $this->queuedEvents()[0]->body['revision']]);
        self::assertSame(Availability::Available, $this->storedResource(ExternalResourceMother::ID)?->availability());
    }

    public function testItUnpublishesTheResourceAndTellsTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::article(status: ResourceStatus::Published));

        $this->post($client, 'cms_externals_unpublish', self::HOST.'/externals/'.ExternalResourceMother::ID.'/unpublish');

        self::assertSame([['key' => 'cms.notices.external.unpublished', 'parameters' => []]], self::successNotices($client));
        self::assertSame([['cms.external.unpublished', ['revision' => 2]]], array_map(static fn ($event): array => [$event->name, $event->body], $this->queuedEvents()));
        self::assertSame(ResourceStatus::Unpublished, $this->storedResource(ExternalResourceMother::ID)?->status());
    }

    public function testItUnpublishesOnlyAPublishedResource(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::article());

        $this->post($client, 'cms_externals_unpublish', self::HOST.'/externals/'.ExternalResourceMother::ID.'/unpublish');

        self::assertSame([['key' => 'errors.external.publication.not_published', 'parameters' => []]], self::errorNotices($client));
    }

    public function testItDiscardsTheChangesAndTheImageOnlyTheyHad(): void
    {
        $client = self::createAuthenticatedClient();
        $onSite = ExternalResourceMother::video()->version();
        $this->saveResource(ExternalResourceMother::video(title: 'Otro título', image: ExternalResourceMother::OTHER_IMAGE, status: ResourceStatus::Published, published: $onSite));
        self::keepImage(ExternalResourceMother::IMAGE);
        self::keepImage(ExternalResourceMother::OTHER_IMAGE);

        $this->post($client, 'cms_externals_discard', self::HOST.'/externals/'.ExternalResourceMother::ID.'/discard');

        self::assertSame([['key' => 'cms.notices.external.discarded', 'parameters' => []]], self::successNotices($client));
        self::assertSame('Un vídeo', $this->storedResource(ExternalResourceMother::ID)?->version()->title()->value());
        self::assertFileExists(self::imageDirectory().'/'.ExternalResourceMother::IMAGE);
        self::assertFileDoesNotExist(self::imageDirectory().'/'.ExternalResourceMother::OTHER_IMAGE);
    }

    public function testItHasNoChangesToDiscardOfAResourceOnTheSiteAsItIs(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video(status: ResourceStatus::Published));

        $this->post($client, 'cms_externals_discard', self::HOST.'/externals/'.ExternalResourceMother::ID.'/discard');

        self::assertSame([['key' => 'errors.external.publication.no_changes_to_discard', 'parameters' => []]], self::errorNotices($client));
    }

    public function testItDeletesAResourceOffTheSiteWithItsImage(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video(status: ResourceStatus::Unpublished));
        self::keepImage(ExternalResourceMother::IMAGE);

        $this->post($client, 'cms_externals_delete', self::HOST.'/externals/'.ExternalResourceMother::ID.'/delete');

        self::assertResponseRedirects('/externals', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.external.deleted', 'parameters' => []]], self::successNotices($client));
        self::assertNull($this->storedResource(ExternalResourceMother::ID));
        self::assertFileDoesNotExist(self::imageDirectory().'/'.ExternalResourceMother::IMAGE);
    }

    #[DataProvider('undeletable')]
    public function testItKeepsAResourceThatIsPublishedOrInUse(ResourceStatus $status, bool $used, string $key): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video(status: $status));

        if ($used) {
            self::getContainer()->get(RelationRepository::class)->replace(ResourceType::Post, '01a1e2a0-0000-7000-8000-0000000000f1', ResourceType::External, [ExternalResourceMother::ID]);
        }

        $this->post($client, 'cms_externals_delete', self::HOST.'/externals/'.ExternalResourceMother::ID.'/delete');

        self::assertResponseRedirects('/externals/'.ExternalResourceMother::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => $key, 'parameters' => []]], self::errorNotices($client));
        self::assertNotNull($this->storedResource(ExternalResourceMother::ID));
    }

    /**
     * @return iterable<string, array{ResourceStatus, bool, string}>
     */
    public static function undeletable(): iterable
    {
        yield 'published' => [ResourceStatus::Published, false, 'errors.external.publication.published_resource'];
        yield 'used by a post' => [ResourceStatus::Draft, true, 'errors.external.publication.used_resource'];
    }
}
