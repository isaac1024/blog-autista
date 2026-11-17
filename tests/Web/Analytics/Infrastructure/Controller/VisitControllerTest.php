<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Response;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermRepository;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Post\Domain\PostIdMother;

/**
 * What a visit records, and what it can't take, is told by
 * VisitRecorderTest, and which requests come from the site by
 * SameOriginRequestTest; here, how the endpoint answers: what it takes, from
 * where, how often, and what it never keeps.
 */
final class VisitControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private const string HOST = 'http://www.blogautista.localhost';
    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e001';
    private const string SECTION = '01a1b801-ce58-723d-8234-b76d9105e011';
    private const string TAG = '01a1b801-ce58-723d-8234-b76d9105e012';
    private const string AUTHOR = '01a1b801-ce58-723d-8234-b76d9105e021';
    private const string BROWSER = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';

    public function testItKeepsAVisitToAPageOnTheSite(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, referrer: 'https://www.google.es/', zone: 'Europe/Madrid');

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([[
            'content_type' => 'page',
            'content_id' => self::PAGE,
            'day' => '2026-11-07',
            'channel' => 'search',
            'source' => 'google.es',
            'device' => 'desktop',
            'country' => 'ES',
        ]], $this->visits());
    }

    /**
     * A post, a section, a tag and an author on the site count their visits
     * as a page does, each by its kind; one off the site, none.
     */
    #[DataProvider('contents')]
    public function testItKeepsAVisitToAnyContentOnTheSite(string $type, string $id, int $status, int $kept): void
    {
        $client = $this->clientWithPage();
        $this->saveContents();

        $this->visit($client, page: $id, type: $type);

        self::assertResponseStatusCodeSame($status);
        self::assertSame(array_fill(0, $kept, [$type, $id]), array_map(static fn (array $visit): array => [$visit['content_type'], $visit['content_id']], $this->visits()));
    }

    /**
     * @return iterable<string, array{string, string, int, int}>
     */
    public static function contents(): iterable
    {
        yield 'a post' => ['post', PostIdMother::DEFAULT_VALUE, Response::HTTP_NO_CONTENT, 1];
        yield 'a section' => ['term', self::SECTION, Response::HTTP_NO_CONTENT, 1];
        yield 'a tag' => ['term', self::TAG, Response::HTTP_NO_CONTENT, 1];
        yield 'an author' => ['author', self::AUTHOR, Response::HTTP_NO_CONTENT, 1];
        yield 'a post by the id of a page' => ['post', self::PAGE, Response::HTTP_NOT_FOUND, 0];
        yield 'a term not on the site' => ['term', self::PAGE, Response::HTTP_NOT_FOUND, 0];
        yield 'an author not on the site' => ['author', self::PAGE, Response::HTTP_NOT_FOUND, 0];
        yield 'a kind the site counts nothing of' => ['image', self::PAGE, Response::HTTP_UNPROCESSABLE_ENTITY, 0];
    }

    /**
     * Which pages are within the site is told by VisitRecorderTest; here,
     * that the site and the CMS are.
     */
    public function testItTakesTheSiteAndTheCmsAsWithinTheSite(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, referrer: self::HOST.'/otra.html');
        $this->visit($client, referrer: 'http://cms.blogautista.localhost/pages', address: '203.0.113.2');

        self::assertSame(['internal', 'internal'], array_column($this->visits(), 'channel'));
    }

    public function testTheAnswerIsKeptByNoCacheAndSetsNoCookie(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    public function testItTakesAVisitFromABrowserThatOnlySaysItsOrigin(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, headers: ['HTTP_ORIGIN' => self::HOST]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertCount(1, $this->visits());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function requestsFromElsewhere(): iterable
    {
        yield 'another site' => [['HTTP_SEC_FETCH_SITE' => 'cross-site']];
        yield 'another origin' => [['HTTP_ORIGIN' => 'https://example.org']];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('requestsFromElsewhere')]
    public function testItRefusesAVisitFromElsewhere(array $headers): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, headers: $headers);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame([], $this->visits());
    }

    public function testItRefusesAVisitToAPageOffTheSite(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, page: '01a1b801-ce58-723d-8234-b76d9105e002');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertSame([], $this->visits());
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function unprocessableVisits(): iterable
    {
        $visit = ['type' => 'page', 'content' => self::PAGE, 'referrer' => '', 'source' => '', 'medium' => '', 'zone' => ''];

        yield 'no page id' => [['content' => 'sobre-mi'] + $visit];
        yield 'a field missing' => [array_diff_key($visit, ['zone' => true])];
        yield 'a page left too long' => [['referrer' => 'https://example.org/'.str_repeat('a', 2029)] + $visit];
    }

    /**
     * @param array<string, string> $fields
     */
    #[DataProvider('unprocessableVisits')]
    public function testItRefusesAVisitItCannotTake(array $fields): void
    {
        $client = $this->clientWithPage();

        $client->request('POST', self::HOST.'/v', $fields, server: ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_USER_AGENT' => self::BROWSER]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSame([], $this->visits());
    }

    public function testItTakesNoMoreThanSixtyVisitsAMinuteFromOneReader(): void
    {
        $client = $this->clientWithPage();

        for ($visit = 1; $visit <= 60; ++$visit) {
            $this->visit($client, address: '203.0.113.7');
            self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        }

        $this->visit($client, address: '203.0.113.7');
        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $this->visit($client, address: '203.0.113.8');
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertCount(61, $this->visits());
    }

    public function testItAnswersNothingButAVisit(): void
    {
        $client = $this->clientWithPage();

        $client->request('GET', self::HOST.'/v');

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    private function clientWithPage(): KernelBrowser
    {
        $client = self::createClient();
        // One kernel for the whole test, so the salt and the limiter keep
        // their state from one visit to the next, as Redis does.
        $client->disableReboot();
        self::mockTime(ClockMother::NOW);
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(id: self::PAGE)));

        return $client;
    }

    /**
     * A post, a section, a tag and an author on the site.
     */
    private function saveContents(): void
    {
        self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublished::fromPrimitives(PostIdMother::DEFAULT_VALUE, CmsPostPublishedMother::body() + ['revision' => 1])));
        $terms = self::getContainer()->get(TermRepository::class);

        foreach ([[self::SECTION, 'section', 'vida-diaria'], [self::TAG, 'tag', 'sueno']] as [$id, $taxonomy, $slug]) {
            $terms->save(Term::publish(CmsTermPublished::fromPrimitives($id, [
                'taxonomy' => $taxonomy,
                'name' => 'Término',
                'slug' => $slug,
                'meta_description' => 'Sobre esto.',
                'robots' => 'index,follow',
                'first_published_at' => '2026-11-07T10:00:00+00:00',
                'last_published_at' => '2026-11-07T10:00:00+00:00',
                'revision' => 1,
            ])));
        }

        self::getContainer()->get(AuthorRepository::class)->save(Author::publish(CmsAuthorPublished::fromPrimitives(self::AUTHOR, [
            'name' => 'Isaac',
            'slug' => 'isaac',
            'avatar' => '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp',
            'bio' => ['version' => 1, 'blocks' => []],
            'meta_description' => 'Quién escribe.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ])));
    }

    /**
     * @param array<string, string> $headers
     */
    private function visit(
        KernelBrowser $client,
        string $page = self::PAGE,
        string $type = 'page',
        string $referrer = '',
        string $source = '',
        string $medium = '',
        string $zone = '',
        string $userAgent = self::BROWSER,
        string $address = '203.0.113.1',
        array $headers = ['HTTP_SEC_FETCH_SITE' => 'same-origin'],
    ): void {
        $client->request(
            'POST',
            self::HOST.'/v',
            ['type' => $type, 'content' => $page, 'referrer' => $referrer, 'source' => $source, 'medium' => $medium, 'zone' => $zone],
            server: ['HTTP_USER_AGENT' => $userAgent, 'REMOTE_ADDR' => $address] + $headers,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visits(): array
    {
        return self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT content_type, content_id, day, channel, source, device, country FROM shared_visit ORDER BY id');
    }
}
