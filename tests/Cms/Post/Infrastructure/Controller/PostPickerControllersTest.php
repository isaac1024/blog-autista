<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Infrastructure\Controller;

use Cms\Tests\Post\Domain\PostIdMother;
use Cms\Tests\Post\Domain\PostMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The posts a field picks one of, such as where a redirect leads: searched
 * a page at a time, and looked up by their ids, as JSON.
 */
final class PostPickerControllersTest extends PostControllerTestCase
{
    private const string OLDER = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0f01';
    private const string NEWER = '01a1da50-2b7c-7d01-8a3f-5c2e9b1d0f02';

    #[DataProvider('endpoints')]
    public function testItRedirectsAnonymousVisitorsToTheLoginForm(string $path): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.$path);

        self::assertResponseRedirects(self::HOST.'/login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function endpoints(): iterable
    {
        yield 'the search' => ['/posts/search'];
        yield 'the lookup' => ['/posts/lookup'];
    }

    /**
     * Which titles have the words is told by DoctrinePostRepositoryTest;
     * here, that the posts come newest first, each as a field shows it.
     */
    public function testItFindsThePostsNewestFirst(): void
    {
        $client = $this->clientWithPosts();

        $client->request('GET', self::HOST.'/posts/search');

        self::assertResponseIsSuccessful();
        self::assertSame(['posts' => [self::picked(self::NEWER, 'Un día de lluvia', 'published', true), self::picked(self::OLDER, PostMother::TITLE, 'draft', false)], 'more' => false], self::json($client));
    }

    /**
     * The post edited is left out: a post doesn't link itself.
     */
    public function testItLeavesOutThePostItIsAskedTo(): void
    {
        $client = $this->clientWithPosts();

        $client->request('GET', self::HOST.'/posts/search?except='.self::NEWER);

        self::assertSame([self::OLDER], array_column((array) (self::json($client)['posts'] ?? []), 'id'));
    }

    public function testItAnswersNotFoundForAPageOfResultsOrAPostThatCannotBe(): void
    {
        $client = $this->clientWithPosts();

        foreach (['?page=0', '?except=post'] as $query) {
            $client->request('GET', self::HOST.'/posts/search'.$query);

            self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * In the order asked, each once; an id of no post, or no id at all, is
     * left out.
     */
    public function testItLooksUpThePostsWithTheIdsAsked(): void
    {
        $client = $this->clientWithPosts();

        $client->request('GET', self::HOST.'/posts/lookup?ids='.implode(',', [self::OLDER, PostIdMother::random()->value(), 'post', '', self::NEWER, self::OLDER]));

        self::assertResponseIsSuccessful();
        self::assertSame(['posts' => [self::picked(self::OLDER, PostMother::TITLE, 'draft', false), self::picked(self::NEWER, 'Un día de lluvia', 'published', true)]], self::json($client));
    }

    private function clientWithPosts(): KernelBrowser
    {
        $client = self::createAuthenticatedClient();
        $client->disableReboot();
        $this->savePost(PostMother::create(id: self::OLDER));
        $this->savePost(PostMother::published(id: self::NEWER, title: 'Un día de lluvia', slug: 'un-dia-de-lluvia'));

        return $client;
    }

    /**
     * @return array{id: string, title: string, status: string, published: bool}
     */
    private static function picked(string $id, string $title, string $status, bool $published): array
    {
        $label = self::getContainer()->get(TranslatorInterface::class)->trans(\sprintf('cms.posts.status.%s.label', $status));

        return ['id' => $id, 'title' => $title, 'status' => $label, 'published' => $published];
    }

    /**
     * @return array<mixed>
     */
    private static function json(KernelBrowser $client): array
    {
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
