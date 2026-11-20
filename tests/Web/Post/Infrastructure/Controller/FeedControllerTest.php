<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Web\Author\Domain\Author;
use Web\Author\Domain\AuthorRepository;
use Web\Author\Domain\CmsAuthorPublished;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;
use Web\Tests\Taxonomy\Domain\SectionSlugs;

/**
 * The feed of the site, in Atom: the latest posts search engines may index,
 * twenty at most, newest first, each leading to its post with the image it
 * opens with while that is on the site. The response is read as the XML a
 * feed reader reads, not as a page.
 */
final class FeedControllerTest extends WebTestCase
{
    use SectionSlugs;

    use ClockSensitiveTrait;

    private const string SITE = 'http://www.blogautista.localhost';

    private const string ATOM = 'http://www.w3.org/2005/Atom';

    private const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105a031';

    private const string OTHER_AUTHOR = '01a1b801-ce58-723d-8234-b76d9105a022';

    public function testItTellsTheLatestPostsSearchEnginesMayIndexNewestFirst(): void
    {
        $client = self::createClient();
        self::saveSectionSlug();
        $posts = self::getContainer()->get(PostRepository::class);

        // Twenty-one may be indexed, the newest on the 21st; one newer asks not to be.
        for ($day = 1; $day <= 21; ++$day) {
            $posts->save(self::post($day, 'index,follow'));
        }

        $posts->save(self::post(25, 'noindex,follow'));
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: CmsPostPublishedMother::OPENING_IMAGE)));

        $entries = $this->entries($client);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/atom+xml; charset=UTF-8');
        self::assertResponseHeaderSame('Cache-Control', 'max-age=0, public, s-maxage=300');
        self::assertCount(20, $entries);
        self::assertSame(self::SITE.'/vida-diaria/2026/11/21/entrada-21.html', $entries[0]['id']);
        self::assertSame(self::SITE.'/vida-diaria/2026/11/02/entrada-2.html', $entries[19]['id']);
        self::assertSame('Entrada 21', $entries[0]['title']);
        self::assertSame('2026-11-21T10:00:00+00:00', $entries[0]['published']);
        self::assertSame('Isaac Abreu', $entries[0]['author']);
        self::assertSame('Qué cambió el primer día con tapones.', $entries[0]['summary']);
        self::assertStringStartsWith(self::SITE.'/media/', $entries[0]['enclosure']);
        self::assertStringEndsWith('-1200w.webp', $entries[0]['enclosure']);
    }

    public function testItTellsNoPostWhileTheSiteHasNone(): void
    {
        $client = self::createClient();
        self::saveSectionSlug();

        self::assertSame([], $this->entries($client));
        self::assertResponseIsSuccessful();
    }

    /**
     * The feed tells what it is: the site, by its home page, named as its
     * host until its settings are published, last updated when its latest
     * post was, in Spanish, at its own address. An entry leads to its post,
     * and tells no summary nor image it has not.
     */
    public function testItTellsWhatTheFeedIsAndEachEntryWhatItHas(): void
    {
        $client = self::createClient();
        self::saveSectionSlug();
        self::getContainer()->get(PostRepository::class)->save(Post::publish(CmsPostPublished::fromPrimitives('01a1e000-0000-7000-8000-000000000001', [
            'title' => 'Sin resumen',
            'slug' => 'sin-resumen',
            'day' => '2026-11-03',
            'robots' => 'index,follow',
            'meta_description' => '',
            'first_published_at' => '2026-11-03T10:00:00+00:00',
            'last_published_at' => '2026-11-05T08:30:00+00:00',
            'revision' => 1,
        ] + CmsPostPublishedMother::body())));

        $client->request('GET', self::SITE.'/feed.xml');
        $xpath = $this->xpath($client);
        $read = static function (string $query) use ($xpath): string {
            $value = $xpath->evaluate('string('.$query.')');
            self::assertIsString($value);

            return $value;
        };

        self::assertSame(
            [self::SITE.'/', 'www.blogautista.localhost', '2026-11-05T08:30:00+00:00', 'es', self::SITE.'/feed.xml', 'application/atom+xml', self::SITE.'/', 'text/html'],
            [$read('/a:feed/a:id'), $read('/a:feed/a:title'), $read('/a:feed/a:updated'), $read('/a:feed/@xml:lang'), $read('/a:feed/a:link[@rel="self"]/@href'), $read('/a:feed/a:link[@rel="self"]/@type'), $read('/a:feed/a:link[@rel="alternate"]/@href'), $read('/a:feed/a:link[@rel="alternate"]/@type')],
        );
        $url = self::SITE.'/vida-diaria/2026/11/03/sin-resumen.html';
        self::assertSame(
            [$url, '2026-11-05T08:30:00+00:00', $url, 'text/html', 0.0, 0.0],
            [$read('/a:feed/a:entry/a:id'), $read('/a:feed/a:entry/a:updated'), $read('/a:feed/a:entry/a:link[@rel="alternate"]/@href'), $read('/a:feed/a:entry/a:link[@rel="alternate"]/@type'), $xpath->evaluate('count(/a:feed/a:entry/a:summary)'), $xpath->evaluate('count(/a:feed/a:entry/a:link[@rel="enclosure"])')],
        );
    }

    /**
     * An entry is summed up as everywhere else on the site: signed by its
     * author as the site has them now, and opening with the image its
     * image's address now leads to, when its own left the site.
     */
    public function testAnEntryIsSignedAndIllustratedAsTheSiteHasThemNow(): void
    {
        $client = self::createClient();
        self::saveSectionSlug();
        self::getContainer()->get(PostRepository::class)->save(self::post(3, 'index,follow'));
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: self::IMAGE, path: '2026/11/bosque.webp')));
        self::redirect('01a1b801-ce58-723d-8234-b76d9105c001', '/media/2026/11/faro.webp', 'image', self::IMAGE, 'image', CmsPostPublishedMother::OPENING_IMAGE);
        self::getContainer()->get(AuthorRepository::class)->save(Author::publish(CmsAuthorPublished::fromPrimitives(CmsPostPublishedMother::AUTHOR, [
            'name' => 'Isaac A.',
            'slug' => 'isaac',
            'avatar' => '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp',
            'bio' => ['version' => 1, 'blocks' => []],
            'meta_description' => 'Quién escribe.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ])));

        $entry = $this->entries($client)[0];

        self::assertSame(['Isaac A.', self::SITE.'/media/2026/11/bosque-1200w.webp'], [$entry['author'], $entry['enclosure']]);
    }

    /**
     * An author off the site signs as the author their address now leads
     * to, and by the name the post kept when that leads nowhere.
     */
    public function testAnAuthorOffTheSiteSignsAsTheAuthorTheirAddressLeadsTo(): void
    {
        $client = self::createClient();
        self::saveSectionSlug();
        self::getContainer()->get(PostRepository::class)->save(self::post(3, 'index,follow'));
        $unsigned = $this->entries($client)[0]['author'];
        self::getContainer()->get(AuthorRepository::class)->save(Author::publish(CmsAuthorPublished::fromPrimitives(self::OTHER_AUTHOR, [
            'name' => 'Ana',
            'slug' => 'ana',
            'avatar' => '01a1da50-9e10-7f03-8c5a-1b2c3d4e5f03.webp',
            'bio' => ['version' => 1, 'blocks' => []],
            'meta_description' => 'Quién escribe.',
            'robots' => 'index,follow',
            'first_published_at' => '2026-11-07T10:00:00+00:00',
            'last_published_at' => '2026-11-07T10:00:00+00:00',
            'revision' => 1,
        ])));
        self::redirect('01a1b801-ce58-723d-8234-b76d9105c002', '/autor/isaac.html', 'author', self::OTHER_AUTHOR, 'author', CmsPostPublishedMother::AUTHOR);

        self::assertSame(['Isaac Abreu', 'Ana'], [$unsigned, $this->entries($client)[0]['author']]);
    }

    /**
     * With no post, the feed was last updated now.
     */
    public function testAFeedWithNoPostWasUpdatedNow(): void
    {
        $client = self::createClient();
        self::saveSectionSlug();
        self::mockTime('2026-11-18 09:15:00 UTC');

        $client->request('GET', self::SITE.'/feed.xml');

        self::assertSame('2026-11-18T09:15:00+00:00', $this->xpath($client)->evaluate('string(/a:feed/a:updated)'));
    }

    private function xpath(KernelBrowser $client): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($client->getInternalResponse()->getContent()));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('a', self::ATOM);

        return $xpath;
    }

    /**
     * The entries of the feed, as a feed reader reads them.
     *
     * @return list<array{id: string, title: string, published: string, author: string, summary: string, enclosure: string}>
     */
    private function entries(KernelBrowser $client): array
    {
        $client->request('GET', self::SITE.'/feed.xml');
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($client->getInternalResponse()->getContent()));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('a', self::ATOM);
        $text = static function (string $query, \DOMNode $entry) use ($xpath): string {
            $value = $xpath->evaluate('string('.$query.')', $entry);
            self::assertIsString($value);

            return $value;
        };
        $nodes = $xpath->query('/a:feed/a:entry');
        self::assertNotFalse($nodes);
        $entries = [];

        foreach ($nodes as $entry) {
            self::assertInstanceOf(\DOMElement::class, $entry);
            $entries[] = [
                'id' => $text('a:id', $entry),
                'title' => $text('a:title', $entry),
                'published' => $text('a:published', $entry),
                'author' => $text('a:author/a:name', $entry),
                'summary' => $text('a:summary', $entry),
                'enclosure' => $text('a:link[@rel="enclosure"]/@href', $entry),
            ];
        }

        return $entries;
    }

    private static function post(int $day, string $robots): Post
    {
        return Post::publish(CmsPostPublished::fromPrimitives(\sprintf('01a1e000-0000-7000-8000-%012d', $day), [
            'title' => 'Entrada '.$day,
            'slug' => 'entrada-'.$day,
            'day' => \sprintf('2026-11-%02d', $day),
            'robots' => $robots,
            'first_published_at' => \sprintf('2026-11-%02dT10:00:00+00:00', $day),
            'last_published_at' => ClockMother::now()->format(\DATE_ATOM),
            'revision' => 1,
        ] + CmsPostPublishedMother::body()));
    }

    private static function redirect(string $id, string $source, string $targetType, string $targetId, string $originType, string $originId): void
    {
        self::getContainer()->get(Connection::class)->insert('shared_redirect', [
            'id' => $id, 'source' => $source, 'target_type' => $targetType, 'target_id' => $targetId,
            'origin_type' => $originType, 'origin_id' => $originId,
            'created_at' => '2026-11-09 09:00:00', 'updated_at' => '2026-11-09 10:00:00', 'version' => 1,
        ]);
    }
}
