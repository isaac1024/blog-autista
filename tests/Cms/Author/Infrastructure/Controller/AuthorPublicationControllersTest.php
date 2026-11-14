<?php

declare(strict_types=1);

namespace Cms\Tests\Author\Infrastructure\Controller;

use Cms\Tests\Author\Domain\AuthorIdMother;
use Cms\Tests\Author\Domain\AuthorMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;

/**
 * Publishing, unpublishing, discarding and deleting an author from its
 * editor, which it goes back to, but deleting, which goes to the list.
 */
final class AuthorPublicationControllersTest extends AuthorControllerTestCase
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
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $route, string $action): void
    {
        $client = self::createClient();
        $author = $this->saveAuthor(AuthorMother::published(id: AuthorIdMother::random()->value(), publishedName: 'Antes'));

        $this->post($client, $route, self::HOST.'/authors/'.$author->id()->value().'/'.$action);

        self::assertResponseRedirects(self::HOST.'/login');
        self::assertEquals($author, $this->storedAuthor($author));
        self::assertSame([], $this->queuedEvents());
    }

    #[DataProvider('actions')]
    public function testItRespondsNotFoundWhenTheAuthorDoesNotExist(string $route, string $action): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, $route, self::HOST.'/authors/'.AuthorIdMother::random()->value().'/'.$action);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function actions(): iterable
    {
        yield 'publishing' => ['cms_authors_publish', 'publish'];
        yield 'unpublishing' => ['cms_authors_unpublish', 'unpublish'];
        yield 'discarding' => ['cms_authors_discard', 'discard'];
        yield 'deleting' => ['cms_authors_delete', 'delete'];
    }

    public function testItPublishesTheAuthorAndTellsTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $author = $this->saveAuthor(AuthorMother::publishable(id: $id));

        $this->post($client, 'cms_authors_publish', self::HOST.'/authors/'.$id.'/publish');

        self::assertResponseRedirects('/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.author.published', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(AuthorMother::published(id: $id), $this->storedAuthor($author));
        $events = $this->queuedEvents();
        self::assertSame([['cms.author.published', $id, AuthorMother::AVATAR]], array_map(static fn ($event): array => [$event->name, $event->aggregateId, $event->body['avatar']], $events));
    }

    public function testItGoesBackAndTellsWhatTheAuthorLacksToBePublished(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $author = $this->saveAuthor(AuthorMother::create(id: $id, bio: AuthorMother::BIO, metaDescription: AuthorMother::META_DESCRIPTION));

        $this->post($client, 'cms_authors_publish', self::HOST.'/authors/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.author.publication.without_avatar', 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($author, $this->storedAuthor($author));
        self::assertSame([], $this->queuedEvents());
    }

    public function testItUnpublishesTheAuthorAndTellsTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $author = $this->saveAuthor(AuthorMother::published(id: $id));

        $this->post($client, 'cms_authors_unpublish', self::HOST.'/authors/'.$id.'/unpublish');

        self::assertResponseRedirects('/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertEquals(AuthorMother::unpublished(id: $id), $this->storedAuthor($author));
        self::assertSame(['cms.author.unpublished'], array_map(static fn ($event): string => $event->name, $this->queuedEvents()));
    }

    public function testItDiscardsTheChangesAndTheAvatarOnlyTheyHad(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $author = $this->saveAuthor(AuthorMother::published(id: $id, avatar: AuthorMother::OTHER_AVATAR, publishedAvatar: AuthorMother::AVATAR));
        self::keepAvatar(AuthorMother::AVATAR);
        self::keepAvatar(AuthorMother::OTHER_AVATAR);

        $this->post($client, 'cms_authors_discard', self::HOST.'/authors/'.$id.'/discard');

        self::assertResponseRedirects('/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.author.discarded', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(AuthorMother::published(id: $id), $this->storedAuthor($author));
        self::assertFileExists(self::avatarDirectory().'/'.AuthorMother::AVATAR);
        self::assertFileDoesNotExist(self::avatarDirectory().'/'.AuthorMother::OTHER_AVATAR);
    }

    public function testItDeletesAnAuthorOffTheSiteWithItsAvatar(): void
    {
        $client = self::createAuthenticatedClient();
        $author = $this->saveAuthor(AuthorMother::unpublished(id: AuthorIdMother::random()->value()));
        self::keepAvatar(AuthorMother::AVATAR);

        $this->post($client, 'cms_authors_delete', self::HOST.'/authors/'.$author->id()->value().'/delete');

        self::assertResponseRedirects('/authors', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.author.deleted', 'parameters' => []]], self::successNotices($client));
        self::assertNull($this->storedAuthor($author));
        self::assertFileDoesNotExist(self::avatarDirectory().'/'.AuthorMother::AVATAR);
    }

    public function testItGoesBackAndKeepsAPublishedAuthor(): void
    {
        $client = self::createAuthenticatedClient();
        $id = AuthorIdMother::random()->value();
        $author = $this->saveAuthor(AuthorMother::published(id: $id));
        self::keepAvatar(AuthorMother::AVATAR);

        $this->post($client, 'cms_authors_delete', self::HOST.'/authors/'.$id.'/delete');

        self::assertResponseRedirects(self::HOST.'/authors/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.author.publication.published_author', 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($author, $this->storedAuthor($author));
        self::assertFileExists(self::avatarDirectory().'/'.AuthorMother::AVATAR);
    }
}
