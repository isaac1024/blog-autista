<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Author\Domain\AuthorId;
use Cms\Author\Domain\AuthorRepository;
use Cms\Post\Domain\PostAddress;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Saving, publishing, unpublishing, discarding and deleting posts, as the
 * editor does from the CMS, with what the rest of the CMS and the site are
 * told.
 */
final class PostChangesControllersTest extends PostControllerTestCase
{
    use ClockSensitiveTrait;
    use QueuedDomainEvents;

    /**
     * What its address does once off the site: nothing, so it isn't found.
     */
    private const array LEAVE_NOT_FOUND = ['address' => ['action' => 'none']];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime(ClockMother::NOW);
    }

    #[DataProvider('actions')]
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $route, string $path): void
    {
        $client = self::createClient();
        $post = $this->savePost(PostMother::published(id: PostIdMother::random()->value(), publishedTitle: 'Antes'));

        $this->post($client, $route, self::HOST.'/posts/'.$post->id()->value().$path, self::form() + self::LEAVE_NOT_FOUND);

        self::assertResponseRedirects(self::HOST.'/login');
        self::assertEquals($post, $this->storedPost($post));
        self::assertSame([], $this->queuedEvents());
    }

    #[DataProvider('actions')]
    public function testItRespondsNotFoundWhenThePostDoesNotExist(string $route, string $path): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, $route, self::HOST.'/posts/'.PostIdMother::random()->value().$path, self::form() + self::LEAVE_NOT_FOUND);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function actions(): iterable
    {
        yield 'saving' => ['cms_posts_update', ''];
        yield 'publishing' => ['cms_posts_publish', '/publish'];
        yield 'unpublishing' => ['cms_posts_unpublish', '/unpublish'];
        yield 'discarding' => ['cms_posts_discard', '/discard'];
        yield 'deleting' => ['cms_posts_delete', '/delete'];
    }

    public function testItSavesThePostAndKeepsWhatItUsesInUse(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::create(id: $id));

        $this->post($client, 'cms_posts_update', self::HOST.'/posts/'.$id, self::form(tags: [PostMother::TAG, PostMother::OTHER_TAG]));

        self::assertResponseRedirects('/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.post.saved', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(PostMother::publishable(id: $id, tags: [PostMother::TAG, PostMother::OTHER_TAG]), $this->storedPost($post));
        $relations = self::getContainer()->get(RelationRepository::class);
        self::assertTrue($relations->isUsed(ResourceType::Term, PostMother::SECTION));
        self::assertTrue($relations->isUsed(ResourceType::Term, PostMother::OTHER_TAG));
        self::assertTrue($relations->isUsed(ResourceType::Author, PostMother::AUTHOR));
        self::assertTrue($relations->isUsed(ResourceType::Image, PostMother::OPENING_IMAGE));
    }

    public function testItGoesBackAndKeepsThePostFiledUnderASectionTheCmsHasNot(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::create(id: $id));

        $this->post($client, 'cms_posts_update', self::HOST.'/posts/'.$id, self::form());

        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.post.classification.unknown_section', 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($post, $this->storedPost($post));
    }

    /**
     * What was written goes back to the editor with why it was refused, for
     * that editor alone, once.
     */
    public function testItKeepsWhatWasSentForTheEditorItGoesBackToOnce(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PostIdMother::random()->value();
        $this->savePost(PostMother::create(id: $id));
        $form = ['title' => 'Un título que no se guardó'] + self::form([PostMother::TAG]);

        $this->post($client, 'cms_posts_update', self::HOST.'/posts/'.$id, $form);
        $kept = self::kept($client);
        $client->request('GET', self::HOST.'/posts/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSame([['editor' => '/posts/'.$id, 'fields' => $form]], $kept);
        self::assertSame([], self::kept($client));
    }

    public function testItPublishesThePostAtTheAddressOfItsSectionAndTellsTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::publishable(id: $id, tags: [PostMother::OTHER_TAG]));

        $this->post($client, 'cms_posts_publish', self::HOST.'/posts/'.$id.'/publish');

        self::assertResponseRedirects('/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.post.published', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(PostMother::published(id: $id, tags: [PostMother::OTHER_TAG]), $this->storedPost($post));
        self::assertSame(
            [['cms.post.published', $id, PostMother::SECTION, '2026-11-07', [PostMother::OTHER_TAG]]],
            array_map(static fn ($event): array => [$event->name, $event->aggregateId, $event->body['section_id'], $event->body['day'], $event->body['tag_ids']], $this->queuedEvents()),
        );
    }

    #[DataProvider('offTheSite')]
    public function testItGoesBackAndTellsWhatThePostLacksToBePublished(bool $sectionOnSite, bool $authorOnSite, bool $openingImageOnSite, string $key): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers($sectionOnSite, $authorOnSite, $openingImageOnSite);
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::publishable(id: $id));

        $this->post($client, 'cms_posts_publish', self::HOST.'/posts/'.$id.'/publish');

        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => $key, 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($post, $this->storedPost($post));
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{bool, bool, bool, string}>
     */
    public static function offTheSite(): iterable
    {
        yield 'its section off the site' => [false, true, true, 'errors.post.publication.section_off_site'];
        yield 'its author off the site' => [true, false, true, 'errors.post.publication.author_off_site'];
        yield 'its opening image off the site' => [true, true, false, 'errors.post.publication.opening_image_off_site'];
    }

    public function testItGoesBackAndTellsAnotherPostOnTheSiteHasTheAddress(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $this->savePost(PostMother::published(id: PostIdMother::random()->value()));
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::publishable(id: $id));

        $this->post($client, 'cms_posts_publish', self::HOST.'/posts/'.$id.'/publish');

        $post = PostMother::publishable(id: $id);
        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.post.address.taken', 'parameters' => ['%date%' => '2026/11/07', '%slug%' => PostMother::SLUG]]], self::errorNotices($client));
        self::assertEquals($post, $this->storedPost($post));
    }

    public function testItUnpublishesThePostAndTellsTheSite(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::published(id: $id));

        $this->post($client, 'cms_posts_unpublish', self::HOST.'/posts/'.$id.'/unpublish', self::LEAVE_NOT_FOUND);

        self::assertResponseRedirects('/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.post.unpublished', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(PostMother::unpublished(id: $id), $this->storedPost($post));
        self::assertSame(['cms.post.unpublished'], array_map(static fn ($event): string => $event->name, $this->queuedEvents()));
    }

    /**
     * Unpublishing frees the address: another post first published that day
     * may take it.
     */
    public function testItLetsAnotherPostTakeTheAddressAPostUnpublishedLeft(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $this->savePost(PostMother::unpublished(id: PostIdMother::random()->value()));
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::publishable(id: $id));

        $this->post($client, 'cms_posts_publish', self::HOST.'/posts/'.$id.'/publish');

        self::assertEquals(
            new PostAddress(ClockMother::now(), new Slug(PostMother::SLUG)),
            $this->storedPost($post)?->publishedAddress(),
        );
    }

    #[DataProvider('refusedActions')]
    public function testItGoesBackAndTellsWhatThePostDoesNotAllow(string $route, string $path, string $key): void
    {
        $client = self::createAuthenticatedClient();
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::create(id: $id));

        $this->post($client, $route, self::HOST.'/posts/'.$id.$path, self::LEAVE_NOT_FOUND);

        // Taking it off the site goes back to where it is asked, which tells it.
        self::assertResponseRedirects(self::HOST.'/posts/'.$id.('/unpublish' === $path ? '/unpublish' : ''), Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => $key, 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($post, $this->storedPost($post));
        self::assertSame([], $this->queuedEvents());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedActions(): iterable
    {
        yield 'unpublishing a draft' => ['cms_posts_unpublish', '/unpublish', 'errors.post.publication.not_published'];
        yield 'discarding the changes of a draft' => ['cms_posts_discard', '/discard', 'errors.post.publication.no_changes_to_discard'];
    }

    public function testItDiscardsTheChanges(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::published(id: $id, title: 'Después', publishedTitle: PostMother::TITLE));

        $this->post($client, 'cms_posts_discard', self::HOST.'/posts/'.$id.'/discard');

        self::assertResponseRedirects('/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'cms.notices.post.discarded', 'parameters' => []]], self::successNotices($client));
        self::assertEquals(PostMother::published(id: $id), $this->storedPost($post));
    }

    public function testItDeletesAPostOffTheSiteAndFreesWhatItUsed(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $id = PostIdMother::random()->value();
        $this->post($client, 'cms_posts_update', self::HOST.'/posts/'.$this->savePost(PostMother::create(id: $id))->id()->value(), self::form());

        $this->post($client, 'cms_posts_delete', self::HOST.'/posts/'.$id.'/delete');

        self::assertResponseRedirects('/posts', Response::HTTP_SEE_OTHER);
        self::assertContains(['key' => 'cms.notices.post.deleted', 'parameters' => []], self::successNotices($client));
        self::assertNull($this->storedPost(PostMother::create(id: $id)));
        self::assertFalse(self::getContainer()->get(RelationRepository::class)->isUsed(ResourceType::Term, PostMother::SECTION));
    }

    public function testItGoesBackAndKeepsAPublishedPost(): void
    {
        $client = self::createAuthenticatedClient();
        $id = PostIdMother::random()->value();
        $post = $this->savePost(PostMother::published(id: $id));

        $this->post($client, 'cms_posts_delete', self::HOST.'/posts/'.$id.'/delete');

        self::assertResponseRedirects(self::HOST.'/posts/'.$id, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.post.publication.published_post', 'parameters' => []]], self::errorNotices($client));
        self::assertEquals($post, $this->storedPost($post));
    }

    /**
     * A post another links to, even a draft, goes back to its editor and
     * stays; once nothing links to it, it goes.
     */
    public function testItKeepsAPostAnotherLinksTo(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();
        $linked = $this->savePost(PostMother::create(id: PostIdMother::random()->value()));
        $linking = PostIdMother::random()->value();
        $this->savePost(PostMother::create(id: $linking));
        $this->post($client, 'cms_posts_update', self::HOST.'/posts/'.$linking, ['content' => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Otro post', 'marks' => [['type' => 'link', 'attrs' => ['type' => 'post', 'id' => $linked->id()->value()]]]],
        ]]]], \JSON_THROW_ON_ERROR)] + self::form());

        $this->post($client, 'cms_posts_delete', self::HOST.'/posts/'.$linked->id()->value().'/delete');

        self::assertResponseRedirects(self::HOST.'/posts/'.$linked->id()->value(), Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.post.publication.used_post', 'parameters' => []]], self::errorNotices($client));
        self::assertNotNull($this->storedPost($linked));
    }

    /**
     * What is posted cannot be deleted while a post, even a draft, uses it.
     */
    public function testItKeepsTheSectionTheTagsAndTheAuthorOfAPost(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers(false, false);
        $id = PostIdMother::random()->value();
        $this->post($client, 'cms_posts_update', self::HOST.'/posts/'.$this->savePost(PostMother::create(id: $id))->id()->value(), self::form(tags: [PostMother::OTHER_TAG]));

        $this->post($client, 'cms_terms_delete', self::HOST.'/sections/'.PostMother::SECTION.'/delete');
        $this->post($client, 'cms_terms_delete', self::HOST.'/tags/'.PostMother::OTHER_TAG.'/delete');
        $this->post($client, 'cms_authors_delete', self::HOST.'/authors/'.PostMother::AUTHOR.'/delete');

        self::assertSame([
            ['key' => 'errors.term.publication.used_term', 'parameters' => []],
            ['key' => 'errors.term.publication.used_term', 'parameters' => []],
            ['key' => 'errors.author.publication.used_author', 'parameters' => []],
        ], self::errorNotices($client));
        self::assertNotNull(self::getContainer()->get(TermRepository::class)->find(new TermId(PostMother::OTHER_TAG)));

        self::assertNotNull(self::getContainer()->get(TermRepository::class)->find(new TermId(PostMother::SECTION)));
        self::assertNotNull(self::getContainer()->get(AuthorRepository::class)->find(new AuthorId(PostMother::AUTHOR)));
    }

    /**
     * What waits in the session for the editor the next page opens.
     *
     * @return array<mixed>
     */
    private static function kept(KernelBrowser $client): array
    {
        $flashes = $client->getRequest()->getSession()->getBag('flashes');
        self::assertInstanceOf(FlashBagInterface::class, $flashes);

        return $flashes->peek('kept_edit');
    }

    /**
     * What the editor posts: the post as PostMother::publishable() has it.
     *
     * @param list<string> $tags
     *
     * @return array<string, mixed>
     */
    private static function form(array $tags = []): array
    {
        return [
            'title' => PostMother::TITLE,
            'slug' => PostMother::SLUG,
            'section' => PostMother::SECTION,
            'author' => PostMother::AUTHOR,
            'tags' => $tags,
            'opening_image' => PostMother::OPENING_IMAGE,
            'content' => json_encode(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hoy probé los tapones en el metro.']]]]], \JSON_THROW_ON_ERROR),
            'meta_description' => PostMother::META_DESCRIPTION,
            'robots' => 'index,follow',
        ];
    }
}
