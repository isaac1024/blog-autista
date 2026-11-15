<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceFinder;
use Cms\External\Application\ExternalResourceResponse;
use Cms\External\Domain\ExternalResourceRepository;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Tests\Infrastructure\Event\QueuedDomainEvents;
use Shared\Tests\Infrastructure\Http\RecordedSites;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adding an external resource from its address: the CMS reads what it is
 * and what it tells of itself, from its provider, the registry of a study
 * or its own page, keeps a copy of its image, and goes on to its editor. An
 * address already kept leads to its resource, and one of the blog itself,
 * or none at all, is refused.
 */
final class ExternalResourceCreateControllerTest extends ExternalResourceControllerTestCase
{
    use QueuedDomainEvents;

    public function testItRedirectsAnonymousVisitorsToTheLoginForm(): void
    {
        $client = self::createClient();

        $this->post($client, 'cms_externals_create', self::HOST.'/externals', ['url' => ExternalResourceMother::VIDEO_URL]);

        self::assertResponseRedirects(self::HOST.'/login');
    }

    public function testItAddsAVideoOfYouTubeWithItsTitleAndACopyOfItsImage(): void
    {
        $client = self::createAuthenticatedClient();
        RecordedSites::answerJson('https://www.youtube.com/oembed?format=json&url='.rawurlencode(ExternalResourceMother::VIDEO_URL), ['title' => 'Never Gonna Give You Up', 'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg']);
        RecordedSites::answerFile('https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', self::PNG, 'image/png');

        $resource = $this->create($client, 'https://youtu.be/dQw4w9WgXcQ?si=compartido');

        self::assertSame(['video', 'youtube', 'https://youtu.be/dQw4w9WgXcQ', 'Never Gonna Give You Up', 'landscape'], [$resource->kind, $resource->provider, $resource->url, $resource->title, $resource->imageShape]);
        self::assertSame([['key' => 'cms.notices.external.created', 'parameters' => []]], self::successNotices($client));
        self::assertImageKept($resource, 1200, 675);
        // A draft tells the site nothing.
        self::assertSame([], $this->queuedEvents());
    }

    public function testItKeepsAVideoWithoutImageWhenTheProviderTellsNone(): void
    {
        $client = self::createAuthenticatedClient();
        RecordedSites::answerJson('https://www.tiktok.com/oembed?url='.rawurlencode('https://www.tiktok.com/@scout2015/video/6718335390845095173'), ['version' => '1.0']);

        $resource = $this->create($client, 'https://www.tiktok.com/@scout2015/video/6718335390845095173');

        self::assertSame(['video', 'tiktok', 'portrait', null, 'tiktok.com'], [$resource->kind, $resource->provider, $resource->imageShape, $resource->image, $resource->title]);
    }

    /**
     * @param array<string, null|bool|int|string> $details
     */
    #[DataProvider('posts')]
    public function testItAddsAPostWithWhatItSaysAndWhoWroteItWhen(string $url, string $provider, string $title, array $details, ?string $language): void
    {
        $client = self::createAuthenticatedClient();
        self::recordPosts();

        $resource = $this->create($client, $url);

        self::assertSame(['social_post', $provider, $title, $details, $language ?? 'es', null], [$resource->kind, $resource->provider, $resource->title, $resource->details, $resource->language, $resource->imageShape]);
    }

    /**
     * @return iterable<string, array{string, string, string, array<string, null|bool|int|string>, ?string}>
     */
    public static function posts(): iterable
    {
        yield 'of Bluesky, by the permanent id of its account' => [
            'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l',
            'bluesky',
            'Publicación de Bluesky en Bluesky',
            ['author' => 'Bluesky', 'handle' => '@bsky.app', 'posted_on' => '2024-10-17', 'text' => "Hola a todo el mundo.\n\nHasta pronto.", 'with_media' => false],
            'en',
        ];
        yield 'of X, with its photos told' => [
            'https://twitter.com/jack/status/20',
            'x',
            'Publicación de jack en X',
            ['author' => 'jack', 'handle' => '@jack', 'posted_on' => '2006-03-21', 'text' => 'just setting up my twttr', 'with_media' => true],
            'en',
        ];
        yield 'of Mastodon, as its server tells it' => [
            'https://mastodon.social/@Gargron/1',
            'mastodon',
            'Publicación de Eugen Rochko en Mastodon',
            ['author' => 'Eugen Rochko', 'handle' => '@Gargron@mastodon.social', 'posted_on' => '2016-03-16', 'text' => "Hello world\nagain", 'with_media' => true],
            'en',
        ];
        yield 'of Threads, as its page tells it, with no day' => [
            'https://www.threads.net/@mosseri/post/DCo4WiOvCB0',
            'threads',
            'Publicación de Adam Mosseri en Threads',
            ['author' => 'Adam Mosseri', 'handle' => '@mosseri', 'posted_on' => null, 'text' => 'We are rebalancing ranking.', 'with_media' => false],
            null,
        ];
    }

    public function testItAddsAnEpisodeOfSpotifyWithItsSquareCover(): void
    {
        $client = self::createAuthenticatedClient();
        $url = 'https://open.spotify.com/intl-es/episode/512ojhOuo1ktJprKbVcKyQ';
        RecordedSites::answerJson('https://open.spotify.com/oembed?url='.rawurlencode('https://open.spotify.com/episode/512ojhOuo1ktJprKbVcKyQ'), ['title' => 'Un episodio', 'thumbnail_url' => 'https://image-cdn.spotifycdn.com/image/portada']);
        RecordedSites::answerFile('https://image-cdn.spotifycdn.com/image/portada', self::PNG, 'image/png');

        $resource = $this->create($client, $url);

        self::assertSame(['audio', 'spotify', 'Un episodio', 'square', ['show' => null]], [$resource->kind, $resource->provider, $resource->title, $resource->imageShape, $resource->details]);
        self::assertImageKept($resource, 640, 640);
    }

    public function testItAddsAnArticleAsItsPageTellsIt(): void
    {
        $client = self::createAuthenticatedClient();
        RecordedSites::answer(ExternalResourceMother::ARTICLE_URL, <<<'HTML'
            <!doctype html><html lang="es-ES"><head>
            <title>Título de la ventana</title>
            <meta property="og:title" content="Una noticia &amp; algo más">
            <meta property="og:description" content="De qué trata la noticia.">
            <meta property="og:site_name" content="El Medio">
            <meta property="og:image" content="/imagenes/portada.png">
            <meta property="article:published_time" content="2026-11-01T08:00:00+01:00">
            </head><body></body></html>
            HTML, 200, ['content-type' => 'text/html; charset=utf-8']);
        RecordedSites::answerFile('https://www.example.org/imagenes/portada.png', self::PNG, 'image/png');

        $resource = $this->create($client, ExternalResourceMother::ARTICLE_URL.'?utm_source=boletin#comentarios');

        self::assertSame(
            ['article', null, ExternalResourceMother::ARTICLE_URL, 'Una noticia & algo más', 'De qué trata la noticia.', 'es', ['publisher' => 'El Medio', 'published_on' => '2026-11-01']],
            [$resource->kind, $resource->provider, $resource->url, $resource->title, $resource->summary, $resource->language, $resource->details],
        );
        self::assertImageKept($resource, 1200, 675);
    }

    public function testItAddsAPdfAsADocumentNamedAfterItsFile(): void
    {
        $client = self::createAuthenticatedClient();
        RecordedSites::answer('https://www.boe.es/informe-anual_2025.pdf', '%PDF-1.7 informe', 200, ['content-type' => 'application/pdf', 'content-length' => '16']);

        $resource = $this->create($client, 'https://www.boe.es/informe-anual_2025.pdf');

        self::assertSame(['document', 'Informe anual 2025', ['publisher' => null, 'bytes' => 16, 'pages' => null]], [$resource->kind, $resource->title, $resource->details]);
    }

    /**
     * @param array<string, null|bool|int|string> $details
     */
    #[DataProvider('studies')]
    public function testItAddsAStudyAsItsRegistryTellsIt(string $url, string $registry, string $answer, string $title, array $details): void
    {
        $client = self::createAuthenticatedClient();
        RecordedSites::answer($registry, $answer, 200, ['content-type' => str_starts_with($answer, '<') ? 'application/atom+xml' : 'application/json']);

        $resource = $this->create($client, $url);

        self::assertSame(['study', $title, $details], [$resource->kind, $resource->title, $resource->details]);
    }

    /**
     * @return iterable<string, array{string, string, string, string, array<string, null|bool|int|string>}>
     */
    public static function studies(): iterable
    {
        $authors = array_map(static fn (int $number): array => ['given' => 'A.', 'family' => 'Autor'.$number], range(1, 7));

        yield 'a DOI, by Crossref, with the first authors listed' => [
            ExternalResourceMother::STUDY_URL,
            'https://api.crossref.org/works/10.1038%2Fnature12373',
            json_encode(['message' => ['title' => ['Nanometre-scale thermometry'], 'author' => $authors, 'container-title' => ['Nature'], 'issued' => ['date-parts' => [[2013, 7, 31]]], 'language' => 'en']], \JSON_THROW_ON_ERROR),
            'Nanometre-scale thermometry',
            ['authors' => 'A. Autor1, A. Autor2, A. Autor3, A. Autor4, A. Autor5, A. Autor6 et al.', 'journal' => 'Nature', 'year' => 2013],
        ];
        yield 'an article of PubMed, by the NCBI' => [
            'https://pubmed.ncbi.nlm.nih.gov/33301246/',
            'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id=33301246',
            json_encode(['result' => ['33301246' => ['title' => 'Safety of a vaccine.', 'authors' => [['name' => 'Polack FP'], ['name' => 'Thomas SJ']], 'fulljournalname' => 'The New England journal of medicine', 'pubdate' => '2020 Dec 31']]], \JSON_THROW_ON_ERROR),
            'Safety of a vaccine.',
            ['authors' => 'Polack FP, Thomas SJ', 'journal' => 'The New England journal of medicine', 'year' => 2020],
        ];
        yield 'a preprint of arXiv, by its API' => [
            'https://arxiv.org/abs/1706.03762v7',
            'https://export.arxiv.org/api/query?id_list=1706.03762',
            '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Attention Is All You Need</title><summary> The dominant models. </summary><published>2017-06-12T17:57:34Z</published><author><name>Ashish Vaswani</name></author><author><name>Noam Shazeer</name></author></entry></feed>',
            'Attention Is All You Need',
            ['authors' => 'Ashish Vaswani, Noam Shazeer', 'journal' => 'arXiv', 'year' => 2017],
        ];
    }

    /**
     * What the address alone tells is kept, and the editor is told to fill
     * the rest: a page of a server that doesn't answer as Mastodon is an
     * article, whatever its address looks like.
     */
    public function testItAddsWhatTheAddressAloneTellsWhenItCannotBeRead(): void
    {
        $client = self::createAuthenticatedClient();

        $resource = $this->create($client, 'https://medium.com/@escritora/12345');

        self::assertSame(['article', null, 'medium.com', ['publisher' => null, 'published_on' => null]], [$resource->kind, $resource->provider, $resource->title, $resource->details]);
        self::assertSame([['key' => 'cms.notices.external.created_unread', 'parameters' => []]], self::errorNotices($client));
    }

    public function testItGoesToTheResourceThatHasTheAddressAlready(): void
    {
        $client = self::createAuthenticatedClient();
        $this->saveResource(ExternalResourceMother::video());

        $this->post($client, 'cms_externals_create', self::HOST.'/externals', ['url' => ExternalResourceMother::VIDEO_URL.'&utm_medium=social']);

        self::assertResponseRedirects('/externals/'.ExternalResourceMother::ID, Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => 'errors.external.url.taken', 'parameters' => ['%id%' => ExternalResourceMother::ID]]], self::errorNotices($client));
        self::assertSame([], RecordedSites::asked());
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('refusedAddresses')]
    public function testItRefusesAnAddressItCannotKeep(string $url, string $key, array $parameters): void
    {
        $client = self::createAuthenticatedClient();

        $this->post($client, 'cms_externals_create', self::HOST.'/externals', ['url' => $url]);

        self::assertResponseRedirects('/externals', Response::HTTP_SEE_OTHER);
        self::assertSame([['key' => $key, 'parameters' => $parameters]], self::errorNotices($client));
        self::assertSame([], self::getContainer()->get(ExternalResourceRepository::class)->findByIds([]));
    }

    /**
     * @return iterable<string, array{string, string, array<string, int|string>}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'none' => ['vídeo de YouTube', 'errors.external.url.not_an_address', []];
        yield 'one with a user' => ['https://yo:secreto@example.org/', 'errors.external.url.not_an_address', []];
        yield 'one of another scheme' => ['ftp://example.org/informe.pdf', 'errors.external.url.not_an_address', []];
        yield 'one too long' => ['https://example.org/'.str_repeat('a', 2000), 'errors.external.url.too_long', ['%max%' => 2000]];
        yield 'one of the blog itself' => ['https://blogautista.localhost/sobre-mi.html', 'errors.external.url.own_site', []];
    }

    private static function recordPosts(): void
    {
        RecordedSites::answerJson('https://embed.bsky.app/oembed?url='.rawurlencode('https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l'), [
            'author_name' => 'Bluesky (@bsky.app)',
            'html' => '<blockquote class="bluesky-embed" data-bluesky-uri="at://did:plc:z72i7hdynmk6r22z27h6tvur/app.bsky.feed.post/3l6oveex3ii2l"><p lang="en">Hola a todo el mundo.'."\n\n".'Hasta pronto.</p>&mdash; <a href="https://bsky.app/profile/did:plc:z72i7hdynmk6r22z27h6tvur">Bluesky (@bsky.app)</a> <a href="https://bsky.app/profile/did:plc:z72i7hdynmk6r22z27h6tvur/post/3l6oveex3ii2l">2024-10-17T07:06:51.491Z</a></blockquote>',
        ]);
        RecordedSites::answerJson('https://publish.twitter.com/oembed?omit_script=true&dnt=true&url='.rawurlencode('https://x.com/jack/status/20'), [
            'author_name' => 'jack',
            'author_url' => 'https://x.com/jack',
            'html' => '<blockquote class="twitter-tweet"><p lang="en" dir="ltr">just setting up my twttr <a href="https://t.co/1">pic.x.com/abc</a></p>&mdash; jack (@jack) <a href="https://x.com/jack/status/20?ref_src=twsrc">March 21, 2006</a></blockquote>',
        ]);
        RecordedSites::answerJson('https://mastodon.social/api/v1/statuses/1', [
            'created_at' => '2016-03-16T14:44:31.580Z',
            'language' => 'en',
            'content' => '<p>Hello world<br>again</p>',
            'account' => ['username' => 'Gargron', 'acct' => 'Gargron', 'display_name' => 'Eugen Rochko'],
            'media_attachments' => [['type' => 'image']],
        ]);
        RecordedSites::answer('https://www.threads.com/@mosseri/post/DCo4WiOvCB0', '<html><head><meta property="og:title" content="Adam Mosseri (&#064;mosseri) en Threads"><meta property="og:description" content="We are rebalancing ranking."></head></html>', 200, ['content-type' => 'text/html']);
    }

    /**
     * Creates the resource of the address, from its editor once created.
     */
    private function create(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $url): ExternalResourceResponse
    {
        $this->post($client, 'cms_externals_create', self::HOST.'/externals', ['url' => $url]);

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/externals/[0-9a-f-]{36}$#', $location);

        return new ExternalResourceFinder(substr($location, \strlen('/externals/')))(self::getContainer()->get(ExternalResourceRepository::class));
    }

    private static function assertImageKept(ExternalResourceResponse $resource, int $width, int $height): void
    {
        self::assertNotNull($resource->image);
        $size = getimagesize(self::imageDirectory().'/'.$resource->image);
        self::assertNotFalse($size);
        self::assertSame(['image/webp', $width, $height], [$size['mime'], $size[0], $size[1]]);
    }
}
