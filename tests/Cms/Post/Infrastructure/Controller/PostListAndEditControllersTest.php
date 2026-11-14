<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Post\Domain\Post;
use Cms\Post\Domain\PostRepository;
use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Http\SignedUrls;
use Symfony\Component\HttpFoundation\Response;

final class PostListAndEditControllersTest extends PostControllerTestCase
{
    #[DataProvider('pages')]
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $path): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.$path);

        self::assertResponseRedirects(self::HOST.'/login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pages(): iterable
    {
        yield 'the list' => ['/posts'];
        yield 'the editor' => ['/posts/'.PostIdMother::DEFAULT_VALUE];
        yield 'the search of tags' => ['/tags/search'];
    }

    #[DataProvider('queries')]
    public function testItOpensTheListOfPosts(string $query): void
    {
        $client = self::createAuthenticatedClient();
        $this->savePost(PostMother::create(id: PostIdMother::random()->value()));
        $this->savePost(PostMother::published(id: PostIdMother::random()->value(), slug: 'en-la-web'));

        $client->request('GET', self::HOST.'/posts'.$query);

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_posts');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function queries(): iterable
    {
        yield 'every post' => [''];
        yield 'a search that finds nothing' => ['?title=nada'];
        yield 'the second page' => ['?page=2'];
    }

    public function testItOpensTheEmptyList(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/posts');

        self::assertResponseIsSuccessful();
    }

    public function testItAnswersNotFoundForAPageBeforeTheFirst(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/posts?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItCreatesThePostAndOpensItInTheEditor(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_posts_create', self::HOST.'/posts', ['title' => 'Un post nuevo']);

        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertResponseStatusCodeSame(Response::HTTP_SEE_OTHER);
        self::assertMatchesRegularExpression('#^/posts/[0-9a-f-]{36}$#', $location);
        self::assertSame([['key' => 'cms.notices.post.created', 'parameters' => []]], self::successNotices($client));
        $post = self::getContainer()->get(PostRepository::class)->find(PostIdMother::create(substr($location, \strlen('/posts/'))));
        self::assertSame(['Un post nuevo', 'post-nuevo'], [$post?->title()->value(), $post?->slug()?->value()]);
    }

    public function testItGoesBackToTheListWhenThePostIsRefused(): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_posts_create', self::HOST.'/posts', ['title' => 'No']);

        self::assertResponseRedirects(self::HOST.'/posts', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.post.title.too_short', 'parameters' => ['%min%' => 3]]], self::errorNotices($client));
    }

    #[DataProvider('posts')]
    public function testItOpensTheEditorOfThePost(Post $post, bool $classified): void
    {
        $client = self::createAuthenticatedClient();

        if ($classified) {
            $this->saveClassifiers();
        }

        $this->savePost($post);

        $client->request('GET', self::HOST.'/posts/'.$post->id()->value());

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_posts_edit', ['id' => $post->id()->value()]);
    }

    /**
     * @return iterable<string, array{Post, bool}>
     */
    public static function posts(): iterable
    {
        yield 'an unfiled draft without a slug' => [PostMother::create(id: PostIdMother::random()->value(), slug: ''), false];
        yield 'a draft with its section, author and tags' => [PostMother::publishable(id: PostIdMother::random()->value(), tags: [PostMother::TAG, PostMother::OTHER_TAG]), true];
        yield 'a published post with changes' => [PostMother::published(id: PostIdMother::random()->value(), publishedTitle: 'Antes'), true];
        yield 'an unpublished post' => [PostMother::unpublished(id: PostIdMother::random()->value()), true];
    }

    public function testItRespondsNotFoundWhenThePostDoesNotExist(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/posts/'.PostIdMother::random()->value());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[DataProvider('previewed')]
    public function testItShowsThePostToWhoeverHoldsAValidLinkWithoutSigningIn(Post $post, bool $onSite): void
    {
        $client = self::createClient();
        $this->saveClassifiers($onSite, $onSite);
        $this->savePost($post);

        $client->request('GET', $this->signed($post->id()->value(), '+1 hour'));

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_posts_preview', ['id' => $post->id()->value()]);
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex,nofollow');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    /**
     * @return iterable<string, array{Post, bool}>
     */
    public static function previewed(): iterable
    {
        yield 'a draft whose section and author are off the site' => [PostMother::publishable(id: PostIdMother::random()->value(), tags: [PostMother::TAG, PostMother::OTHER_TAG]), false];
        yield 'a published post whose section and author are on it' => [PostMother::published(id: PostIdMother::random()->value(), tags: [PostMother::TAG]), true];
        yield 'an unfiled draft' => [PostMother::create(id: PostIdMother::random()->value()), true];
    }

    #[DataProvider('refusedLinks')]
    public function testItRefusesALinkNotSignedOrExpired(bool $signed): void
    {
        $client = self::createClient();
        $post = $this->savePost(PostMother::create(id: PostIdMother::random()->value()));

        $client->request('GET', $signed ? $this->signed($post->id()->value(), '-1 minute') : self::HOST.'/preview/posts/'.$post->id()->value());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function refusedLinks(): iterable
    {
        yield 'expired' => [true];
        yield 'unsigned' => [false];
    }

    public function testItRespondsNotFoundForAValidLinkToAPostThatDoesNotExist(): void
    {
        $client = self::createClient();

        $client->request('GET', $this->signed(PostIdMother::random()->value(), '+1 hour'));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItFindsTheTagsWhoseNameHasTheWordsAsJson(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveClassifiers();

        $client->request('GET', self::HOST.'/tags/search');

        self::assertResponseIsSuccessful();
        $answer = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($answer);
        self::assertIsArray($answer['terms']);
        // Newest first, each with whether the site shows it; its status is told in words for the editor.
        self::assertSame(
            [[PostMother::OTHER_TAG, 'Ruido', false], [PostMother::TAG, 'Sensorialidad', true]],
            array_map(static fn (mixed $term): array => \is_array($term) ? [$term['id'] ?? null, $term['name'] ?? null, $term['published'] ?? null] : [], $answer['terms']),
        );
        self::assertFalse($answer['more']);
    }

    public function testItAnswersNotFoundForAPageOfTagsBeforeTheFirst(): void
    {
        $client = self::createAuthenticatedClient();

        $client->request('GET', self::HOST.'/tags/search?page=0');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function signed(string $id, string $expiresIn): string
    {
        return self::getContainer()->get(SignedUrls::class)->sign(\sprintf('%s/preview/posts/%s', self::HOST, $id), new \DateTimeImmutable($expiresIn));
    }
}
