<?php

declare(strict_types=1);

namespace Web\Tests\Post\Infrastructure\Controller;

use Shared\Tests\Domain\ClockMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Post\Domain\CmsPostPublished;
use Web\Post\Domain\Post;
use Web\Post\Domain\PostRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Post\Domain\CmsPostPublishedMother;

/**
 * The feed of the site, in Atom: the latest posts search engines may index,
 * twenty at most, newest first, each leading to its post with the image it
 * opens with while that is on the site. The response is read as the XML a
 * feed reader reads, not as a page.
 */
final class FeedControllerTest extends WebTestCase
{
    private const string SITE = 'http://www.blogautista.localhost';

    private const string ATOM = 'http://www.w3.org/2005/Atom';

    public function testItTellsTheLatestPostsSearchEnginesMayIndexNewestFirst(): void
    {
        $client = self::createClient();
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

        self::assertSame([], $this->entries($client));
        self::assertResponseIsSuccessful();
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
}
