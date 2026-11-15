<?php

declare(strict_types=1);

namespace Cms\Tests\External\Infrastructure\Reading;

use Cms\External\Domain\CheckOutcome;
use Cms\External\Domain\Language;
use Cms\External\Domain\Reading;
use Cms\External\Domain\ResourceUrl;
use Cms\External\Infrastructure\Reading\HttpResourceReader;
use Cms\External\Infrastructure\Reading\JsonReading;
use Cms\External\Infrastructure\Reading\PageReading;
use Cms\External\Infrastructure\Reading\PostReading;
use Cms\External\Infrastructure\Reading\ProviderReading;
use Cms\External\Infrastructure\Reading\StudyReading;
use Cms\Tests\External\Domain\ExternalResourceMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\External\ResourceKind;
use Shared\Infrastructure\Http\RemoteHttp;
use Shared\Tests\Infrastructure\Http\RecordedSites;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * What reading an address tells of the resource there, from its provider,
 * the registry of a study or its own page, and what checking it finds,
 * with the sites answering as they were recorded answering.
 */
final class HttpResourceReaderTest extends TestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        RecordedSites::forget();

        parent::tearDown();
    }

    /**
     * @param \Closure(): void $record what the sites answer
     */
    #[DataProvider('readings')]
    #[DataProvider('studies')]
    #[DataProvider('pages')]
    #[DataProvider('posts')]
    public function testItReadsWhatTheResourceTellsOfItself(string $url, \Closure $record, Reading $expected): void
    {
        $record();

        self::assertEquals($expected, self::reader()->read(new ResourceUrl($url)));
    }

    /**
     * @return iterable<string, array{string, \Closure(): void, Reading}>
     */
    public static function readings(): iterable
    {
        yield 'a video of YouTube, by its oEmbed, its largest image first' => [
            'https://youtu.be/dQw4w9WgXcQ?si=compartido',
            static fn () => RecordedSites::answerJson(self::oembed('https://www.youtube.com/oembed?format=json&url=', ExternalResourceMother::VIDEO_URL), ['title' => 'Never Gonna Give You Up', 'thumbnail_url' => 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg']),
            new Reading(ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, 'Never Gonna Give You Up', null, null, [], ['https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg', 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg'], true),
        ];
        yield 'a video of TikTok whose provider tells no image' => [
            'https://www.tiktok.com/@scout2015/video/6718335390845095173',
            static fn () => RecordedSites::answerJson(self::oembed('https://www.tiktok.com/oembed?url=', 'https://www.tiktok.com/@scout2015/video/6718335390845095173'), ['version' => '1.0']),
            new Reading(ResourceKind::Video, 'tiktok', 'scout2015/6718335390845095173', null, null, null, [], [], true),
        ];
        yield 'a post of Bluesky, by the permanent id of its account' => [
            'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l',
            static fn () => RecordedSites::answerJson(self::oembed('https://embed.bsky.app/oembed?url=', 'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l'), [
                'author_name' => 'Bluesky (@bsky.app)',
                'html' => '<blockquote class="bluesky-embed" data-bluesky-uri="at://did:plc:z72i7hdynmk6r22z27h6tvur/app.bsky.feed.post/3l6oveex3ii2l"><p lang="en">Hola a todo el mundo.'."\n\n".'Hasta pronto.</p>&mdash; <a href="https://bsky.app/profile/did:plc:z72i7hdynmk6r22z27h6tvur">Bluesky (@bsky.app)</a> <a href="https://bsky.app/profile/did:plc:z72i7hdynmk6r22z27h6tvur/post/3l6oveex3ii2l">2024-10-17T07:06:51.491Z</a></blockquote>',
            ]),
            self::post('bluesky', 'did:plc:z72i7hdynmk6r22z27h6tvur/3l6oveex3ii2l', 'Publicación de Bluesky en Bluesky', Language::English, ['author' => 'Bluesky', 'handle' => '@bsky.app', 'posted_on' => '2024-10-17', 'text' => "Hola a todo el mundo.\n\nHasta pronto.", 'with_media' => false]),
        ];
        yield 'a post of X, with its photos told' => [
            'https://twitter.com/jack/status/20',
            static fn () => RecordedSites::answerJson(self::oembed('https://publish.twitter.com/oembed?omit_script=true&dnt=true&url=', 'https://x.com/jack/status/20'), [
                'author_name' => 'jack',
                'author_url' => 'https://x.com/jack',
                'html' => '<blockquote class="twitter-tweet"><p lang="en" dir="ltr">just setting up my twttr <a href="https://t.co/1">pic.x.com/abc</a></p>&mdash; jack (@jack) <a href="https://x.com/jack/status/20?ref_src=twsrc">March 21, 2006</a></blockquote>',
            ]),
            self::post('x', 'jack/20', 'Publicación de jack en X', Language::English, ['author' => 'jack', 'handle' => '@jack', 'posted_on' => '2006-03-21', 'text' => 'just setting up my twttr', 'with_media' => true]),
        ];
        yield 'a post of Mastodon, as its server tells it' => [
            'https://mastodon.social/@Gargron/1',
            static fn () => RecordedSites::answerJson('https://mastodon.social/api/v1/statuses/1', [
                'created_at' => '2016-03-16T14:44:31.580Z',
                'language' => 'en',
                'content' => '<p>Hello world<br>again</p>',
                'account' => ['username' => 'Gargron', 'acct' => 'Gargron', 'display_name' => 'Eugen Rochko'],
                'media_attachments' => [['type' => 'image']],
            ]),
            self::post('mastodon', 'mastodon.social/Gargron/1', 'Publicación de Eugen Rochko en Mastodon', Language::English, ['author' => 'Eugen Rochko', 'handle' => '@Gargron@mastodon.social', 'posted_on' => '2016-03-16', 'text' => "Hello world\nagain", 'with_media' => true]),
        ];
        yield 'a post of Threads, as its page tells it, with no day' => [
            'https://www.threads.net/@mosseri/post/DCo4WiOvCB0',
            static fn () => RecordedSites::answer('https://www.threads.com/@mosseri/post/DCo4WiOvCB0', '<html><head><meta property="og:title" content="Adam Mosseri (&#064;mosseri) en Threads"><meta property="og:description" content="We are rebalancing ranking."></head></html>', 200, ['content-type' => 'text/html']),
            self::post('threads', 'mosseri/DCo4WiOvCB0', 'Publicación de Adam Mosseri en Threads', null, ['author' => 'Adam Mosseri', 'handle' => '@mosseri', 'text' => 'We are rebalancing ranking.', 'posted_on' => null, 'with_media' => false]),
        ];
        yield 'an episode of Spotify, by its address without the country' => [
            'https://open.spotify.com/intl-es/episode/512ojhOuo1ktJprKbVcKyQ',
            static fn () => RecordedSites::answerJson(self::oembed('https://open.spotify.com/oembed?url=', 'https://open.spotify.com/episode/512ojhOuo1ktJprKbVcKyQ'), ['title' => 'Un episodio', 'thumbnail_url' => 'https://image-cdn.spotifycdn.com/image/portada']),
            new Reading(ResourceKind::Audio, 'spotify', 'episode/512ojhOuo1ktJprKbVcKyQ', 'Un episodio', null, null, ['show' => null], ['https://image-cdn.spotifycdn.com/image/portada'], true),
        ];
        yield 'an article, as its page tells it, its image at the address of the page' => [
            ExternalResourceMother::ARTICLE_URL.'?utm_source=boletin#comentarios',
            static fn () => RecordedSites::answer(ExternalResourceMother::ARTICLE_URL, <<<'HTML'
                <!doctype html><html lang="es-ES"><head>
                <title>Título de la ventana</title>
                <meta property="og:title" content="Una noticia &amp; algo más">
                <meta property="og:description" content="De qué trata la noticia.">
                <meta property="og:site_name" content="El Medio">
                <meta property="og:image" content="/imagenes/portada.png">
                <meta property="article:published_time" content="2026-11-01T08:00:00+01:00">
                </head><body></body></html>
                HTML, 200, ['content-type' => 'text/html; charset=utf-8']),
            new Reading(ResourceKind::Article, null, null, 'Una noticia & algo más', 'De qué trata la noticia.', Language::Spanish, ['publisher' => 'El Medio', 'published_on' => '2026-11-01'], ['https://www.example.org/imagenes/portada.png'], true),
        ];
        yield 'a PDF, as a document named after its file' => [
            'https://www.boe.es/informe-anual_2025.pdf',
            static fn () => RecordedSites::answer('https://www.boe.es/informe-anual_2025.pdf', '%PDF-1.7 informe', 200, ['content-type' => 'application/pdf', 'content-length' => '16']),
            new Reading(ResourceKind::Document, null, null, 'Informe anual 2025', null, null, ['publisher' => null, 'bytes' => 16, 'pages' => null], [], true),
        ];
        yield 'a DOI, by Crossref, with the first authors listed' => [
            ExternalResourceMother::STUDY_URL,
            static fn () => RecordedSites::answerJson('https://api.crossref.org/works/10.1038%2Fnature12373', ['message' => [
                'title' => ['Nanometre-scale thermometry'],
                'author' => array_map(static fn (int $number): array => ['given' => 'A.', 'family' => 'Autor'.$number], range(1, 7)),
                'container-title' => ['Nature'],
                'issued' => ['date-parts' => [[2013, 7, 31]]],
                'language' => 'en',
            ]]),
            self::study('Nanometre-scale thermometry', null, Language::English, ['authors' => 'A. Autor1, A. Autor2, A. Autor3, A. Autor4, A. Autor5, A. Autor6 et al.', 'journal' => 'Nature', 'year' => 2013]),
        ];
        yield 'an article of PubMed, by the NCBI' => [
            'https://pubmed.ncbi.nlm.nih.gov/33301246/',
            static fn () => RecordedSites::answerJson('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id=33301246', ['result' => ['33301246' => [
                'title' => 'Safety of a vaccine.',
                'authors' => [['name' => 'Polack FP'], ['name' => 'Thomas SJ']],
                'fulljournalname' => 'The New England journal of medicine',
                'pubdate' => '2020 Dec 31',
            ]]]),
            self::study('Safety of a vaccine.', null, null, ['authors' => 'Polack FP, Thomas SJ', 'journal' => 'The New England journal of medicine', 'year' => 2020]),
        ];
        yield 'a preprint of arXiv, by its API, whatever its version' => [
            'https://arxiv.org/abs/1706.03762v7',
            static fn () => RecordedSites::answer('https://export.arxiv.org/api/query?id_list=1706.03762', '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Attention Is All You Need</title><summary> The dominant models. </summary><published>2017-06-12T17:57:34Z</published><author><name>Ashish Vaswani</name></author><author><name>Noam Shazeer</name></author></entry></feed>', 200, ['content-type' => 'application/atom+xml']),
            self::study('Attention Is All You Need', 'The dominant models.', null, ['authors' => 'Ashish Vaswani, Noam Shazeer', 'journal' => 'arXiv', 'year' => 2017]),
        ];
        yield 'a page that cannot be read, which a server that is no Mastodon makes an article' => [
            'https://medium.com/@escritora/12345',
            static function (): void {},
            new Reading(ResourceKind::Article, null, null, null, null, null, [], [], false),
        ];
        yield 'a video whose provider cannot be read, which stays its video' => [
            'https://www.youtube.com/watch?v=aaaaaaaaaaa',
            static function (): void {},
            new Reading(ResourceKind::Video, 'youtube', 'aaaaaaaaaaa', null, null, null, [], [], false),
        ];
        yield 'a study whose registry cannot be read, which stays a study' => [
            'https://doi.org/10.1000/xyz',
            static function (): void {},
            new Reading(ResourceKind::Study, null, null, null, null, null, [], [], false),
        ];
    }

    /**
     * @return iterable<string, array{string, \Closure(): void, Reading}>
     */
    public static function studies(): iterable
    {
        $crossref = static fn (string $doi, array $message): \Closure => static fn () => RecordedSites::answerJson('https://api.crossref.org/works/'.rawurlencode($doi), ['message' => $message]);
        $names = array_map(static fn (int $number): string => str_repeat('Ñ', 59).$number, range(1, 6));
        $unread = new Reading(ResourceKind::Study, null, null, null, null, null, [], [], false);

        yield 'a DOI of an organization and of authors without a family name, dated by its publication, in the journal of its publisher' => [
            'https://doi.org/10.1000/xyz',
            $crossref('10.1000/xyz', [
                'title' => ['  Un   informe &amp; <i>más</i> '],
                'author' => [['name' => 'OMS'], ['given' => 'Ana', 'family' => 'Ruiz'], ['given' => 'Solo'], 'nadie', ['given' => '']],
                'published' => ['date-parts' => [[2021, 3]]],
                'publisher' => 'Organización Mundial',
                'language' => 'EN-GB',
                'abstract' => "<p>Un  resumen.</p>\n<p>Otro.</p>",
            ]),
            self::study('Un informe & más', 'Un resumen. Otro.', Language::English, ['authors' => 'OMS, Ana Ruiz, Solo', 'journal' => 'Organización Mundial', 'year' => 2021]),
        ];
        yield 'a DOI of as many authors as are listed, dated by its issue, with what is too long cut' => [
            'https://doi.org/10.1000/largo',
            $crossref('10.1000/largo', [
                'title' => ['Largo'],
                'author' => array_map(static fn (string $name): array => ['family' => $name], $names),
                'issued' => ['date-parts' => [[2010]]],
                'published' => ['date-parts' => [[2011]]],
                'container-title' => [str_repeat('á', 250)],
                'publisher' => 'Otra',
                'abstract' => str_repeat('é', 400),
            ]),
            self::study('Largo', str_repeat('é', 300), null, ['authors' => mb_substr(implode(', ', $names), 0, 300), 'journal' => str_repeat('á', 200), 'year' => 2010]),
        ];
        yield 'a DOI whose registry answers without the work' => ['https://doi.org/10.1000/xyz', static fn () => RecordedSites::answerJson('https://api.crossref.org/works/10.1000%2Fxyz', ['status' => 'ok']), $unread];
        yield 'a DOI whose registry fails' => ['https://doi.org/10.1000/xyz', static fn () => RecordedSites::answerJson('https://api.crossref.org/works/10.1000%2Fxyz', ['message' => ['title' => ['Un informe']]], 500), $unread];
        yield 'a DOI of a work without a title' => ['https://doi.org/10.1000/xyz', $crossref('10.1000/xyz', ['author' => [['family' => 'Ruiz']]]), $unread];
        yield 'an article of PubMed by the abbreviation of its journal, of no year, without its unnamed authors' => [
            'https://pubmed.ncbi.nlm.nih.gov/123/',
            static fn () => RecordedSites::answerJson('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id=123', ['result' => ['123' => [
                'title' => 'Un ensayo.',
                'authors' => [['name' => 'Ruiz A'], ['name' => ' '], 'nadie'],
                'source' => 'N Engl J Med',
                'pubdate' => 'Invierno',
            ]]]),
            self::study('Un ensayo.', null, null, ['authors' => 'Ruiz A', 'journal' => 'N Engl J Med', 'year' => null]),
        ];
        yield 'an article of PubMed the NCBI does not have' => ['https://pubmed.ncbi.nlm.nih.gov/123/', static fn () => RecordedSites::answerJson('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id=123', ['result' => []]), $unread];
        yield 'a preprint of arXiv with its authors trimmed, of no day and no summary' => [
            'https://arxiv.org/abs/2101.00001',
            static fn () => RecordedSites::answer('https://export.arxiv.org/api/query?id_list=2101.00001', '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>  Un   título </title><author><name>  Ana Ruiz  </name></author><author><name> </name></author></entry></feed>', 200, ['content-type' => 'application/atom+xml']),
            self::study('Un título', null, null, ['authors' => 'Ana Ruiz', 'journal' => 'arXiv', 'year' => null]),
        ];
        yield 'a preprint arXiv does not find' => ['https://arxiv.org/abs/2101.00001', static fn () => RecordedSites::answer('https://export.arxiv.org/api/query?id_list=2101.00001', '<feed xmlns="http://www.w3.org/2005/Atom"></feed>', 200, ['content-type' => 'application/atom+xml']), $unread];
        yield 'a preprint whose API fails' => ['https://arxiv.org/abs/2101.00001', static fn () => RecordedSites::answer('https://export.arxiv.org/api/query?id_list=2101.00001', '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Un título</title></entry></feed>', 503, ['content-type' => 'application/atom+xml']), $unread];
        yield 'a preprint whose API answers with no feed' => ['https://arxiv.org/abs/2101.00001', static fn () => RecordedSites::answer('https://export.arxiv.org/api/query?id_list=2101.00001', 'no es un feed', 200, ['content-type' => 'text/plain']), $unread];
        yield 'a preprint whose feed is too long to read whole' => ['https://arxiv.org/abs/2101.00001', static fn () => RecordedSites::answer('https://export.arxiv.org/api/query?id_list=2101.00001', '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Un título</title><summary>'.str_repeat('a', 2_000_000).'</summary></entry></feed>', 200, ['content-type' => 'application/atom+xml']), $unread];
    }

    /**
     * @return iterable<string, array{string, \Closure(): void, Reading}>
     */
    public static function pages(): iterable
    {
        $page = static fn (string $url, string $html, string $type = 'text/html; charset=utf-8', int $status = 200): \Closure => static fn () => RecordedSites::answer(new ResourceUrl($url)->value(), $html, $status, ['content-type' => $type]);
        $unread = new Reading(ResourceKind::Article, null, null, null, null, null, [], [], false);

        yield 'a page that tells its title in its window, its language by its locale, and its image by the folder of the page' => [
            'https://www.example.org/noticias/hoy.html',
            $page('https://www.example.org/noticias/hoy.html', <<<'HTML'
                <html><head><title>  Título   de la ventana </title>
                <meta property="og:description" content="  ">
                <meta name="description" content="  Resumen  &amp; más ">
                <meta name="description" content="Otro resumen">
                <meta name="application-name" content="El Medio">
                <meta property="OG:IMAGE" content="portada.png">
                <meta property="og:locale" content="es_ES">
                <meta name="date" content="2026-11-01T10:00:00Z">
                </head><body></body></html>
                HTML),
            new Reading(ResourceKind::Article, null, null, 'Título de la ventana', 'Resumen & más', Language::Spanish, ['publisher' => 'El Medio', 'published_on' => '2026-11-01'], ['https://www.example.org/noticias/portada.png'], true),
        ];
        yield 'a page that tells its title for sharing, its secure image wherever it is, and its language in capitals' => [
            ExternalResourceMother::ARTICLE_URL,
            $page(ExternalResourceMother::ARTICLE_URL, '<html lang=" PT-br "><head><meta name="twitter:title" content="Un título"><meta property="og:image" content="/ignorada.png"><meta property="og:image:secure_url" content="//cdn.example.org/a.png"><meta property="article:published_time" content="1 de noviembre"></head></html>'),
            new Reading(ResourceKind::Article, null, null, 'Un título', null, Language::Portuguese, ['publisher' => null, 'published_on' => null], ['https://cdn.example.org/a.png'], true),
        ];
        yield 'a page whose language is no code, with an image at an address of its own' => [
            ExternalResourceMother::ARTICLE_URL,
            $page(ExternalResourceMother::ARTICLE_URL, '<html lang="english"><head><meta property="og:title" content="Un título"><meta property="og:image" content="HTTPS://img.example.org/a.png"></head></html>'),
            new Reading(ResourceKind::Article, null, null, 'Un título', null, null, ['publisher' => null, 'published_on' => null], ['HTTPS://img.example.org/a.png'], true),
        ];
        yield 'a page a redirect took elsewhere, on a port of its own, whose image is by the page it landed on' => [
            'https://example.org:8443/a/b.html',
            static fn () => RecordedSites::answerRedirected('https://example.org:8443/a/b.html', 'https://example.org:8443/c/d.html', '<html><head><meta property="og:title" content="Un título"><meta property="og:image" content="img/e.png"></head></html>', 'text/html'),
            new Reading(ResourceKind::Article, null, null, 'Un título', null, null, ['publisher' => null, 'published_on' => null], ['https://example.org:8443/c/img/e.png'], true),
        ];
        yield 'a page in XHTML' => [
            ExternalResourceMother::ARTICLE_URL,
            $page(ExternalResourceMother::ARTICLE_URL, '<html><head><meta property="og:title" content="Un título"></head></html>', 'application/xhtml+xml; charset=utf-8'),
            new Reading(ResourceKind::Article, null, null, 'Un título', null, null, ['publisher' => null, 'published_on' => null], [], true),
        ];
        yield 'a page whose server fails' => [ExternalResourceMother::ARTICLE_URL, $page(ExternalResourceMother::ARTICLE_URL, '<html><head><title>Error</title></head></html>', status: 500), $unread];
        yield 'an address that answers with an image' => [ExternalResourceMother::ARTICLE_URL, $page(ExternalResourceMother::ARTICLE_URL, 'png', 'image/png'), $unread];
        yield 'a PDF that tells not how long it is, named after its file' => [
            'https://www.boe.es/informe_anual-2025%20final.pdf',
            $page('https://www.boe.es/informe_anual-2025%20final.pdf', '%PDF', 'application/pdf'),
            new Reading(ResourceKind::Document, null, null, 'Informe anual 2025 final', null, null, ['publisher' => null, 'bytes' => 4, 'pages' => null], [], true),
        ];
        yield 'a PDF too long to read whole, named after its file in capitals' => [
            'https://www.boe.es/%C3%B1and%C3%BA.pdf',
            $page('https://www.boe.es/%C3%B1and%C3%BA.pdf', str_repeat('a', 1_000_001), 'application/pdf'),
            new Reading(ResourceKind::Document, null, null, 'Ñandú', null, null, ['publisher' => null, 'bytes' => null, 'pages' => null], [], true),
        ];
        yield 'a PDF that cannot be read, named after its file' => [
            'https://www.boe.es/Informe.PDF',
            static function (): void {},
            new Reading(ResourceKind::Document, null, null, 'Informe', null, null, [], [], false),
        ];
    }

    /**
     * @return iterable<string, array{string, \Closure(): void, Reading}>
     */
    public static function posts(): iterable
    {
        $bluesky = static fn (array $data): \Closure => static fn () => RecordedSites::answerJson(self::oembed('https://embed.bsky.app/oembed?url=', 'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l'), $data);
        $mastodon = static fn (array $data): \Closure => static fn () => RecordedSites::answerJson('https://mastodon.social/api/v1/statuses/1', $data);

        yield 'a post of Bluesky by the handle of its account, of no day, written by a name alone' => [
            'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l',
            $bluesky(['author_name' => ' Bluesky ', 'html' => '<blockquote class="bluesky-embed"><p>Hola<br>adiós</p>&mdash; Bluesky</blockquote>']),
            self::post('bluesky', 'bsky.app/3l6oveex3ii2l', 'Publicación de Bluesky en Bluesky', null, ['author' => 'Bluesky', 'handle' => null, 'posted_on' => null, 'text' => "Hola\nadiós", 'with_media' => false]),
        ];
        yield 'a post of Bluesky whose author is told by a handle alone' => [
            'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l',
            $bluesky(['author_name' => '(@solo.bsky.social)', 'html' => '<blockquote><p lang="ES">Hola</p></blockquote>']),
            self::post('bluesky', 'bsky.app/3l6oveex3ii2l', 'Publicación de @solo.bsky.social en Bluesky', Language::Spanish, ['author' => null, 'handle' => '@solo.bsky.social', 'posted_on' => null, 'text' => 'Hola', 'with_media' => false]),
        ];
        yield 'a post of Bluesky whose provider tells no text of it' => [
            'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l',
            $bluesky(['author_name' => 'Bluesky']),
            new Reading(ResourceKind::SocialPost, 'bluesky', 'bsky.app/3l6oveex3ii2l', null, null, null, [], [], false),
        ];
        yield 'a post of X without photos, of no day nor account' => [
            'https://x.com/jack/status/20',
            static fn () => RecordedSites::answerJson(self::oembed('https://publish.twitter.com/oembed?omit_script=true&dnt=true&url=', 'https://x.com/jack/status/20'), ['author_name' => 'jack', 'html' => '<blockquote><p lang="ES">Hola <a href="https://t.co/1">enlace</a></p>&mdash; jack</blockquote>']),
            self::post('x', 'jack/20', 'Publicación de jack en X', Language::Spanish, ['author' => 'jack', 'handle' => null, 'posted_on' => null, 'text' => 'Hola enlace', 'with_media' => false]),
        ];
        yield 'a post of Mastodon from an account of another server, of no day nor media, with its text cut' => [
            'https://mastodon.social/@Gargron/1',
            $mastodon(['language' => 'EN-us', 'content' => '<p>'.str_repeat('ñ', 3100).'</p>', 'account' => ['username' => 'Gargron', 'acct' => 'Gargron@otro.social', 'display_name' => ' '], 'media_attachments' => []]),
            self::post('mastodon', 'mastodon.social/Gargron/1', 'Publicación de Gargron en Mastodon', Language::English, ['author' => 'Gargron', 'handle' => '@Gargron@otro.social', 'posted_on' => null, 'text' => str_repeat('ñ', 3000), 'with_media' => false]),
        ];
        yield 'a post of Threads whose page tells no handle' => [
            'https://www.threads.com/@mosseri/post/DCo4WiOvCB0',
            static fn () => RecordedSites::answer('https://www.threads.com/@mosseri/post/DCo4WiOvCB0', '<html><head><meta property="og:title" content="Un perfil"></head></html>', 200, ['content-type' => 'text/html']),
            self::post('threads', 'mosseri/DCo4WiOvCB0', 'Publicación de Un perfil en Threads', null, ['author' => 'Un perfil', 'handle' => null, 'text' => null, 'posted_on' => null, 'with_media' => false]),
        ];
        yield 'a post of Threads whose page fails' => [
            'https://www.threads.com/@mosseri/post/DCo4WiOvCB0',
            static fn () => RecordedSites::answer('https://www.threads.com/@mosseri/post/DCo4WiOvCB0', '<html></html>', 500, ['content-type' => 'text/html']),
            new Reading(ResourceKind::SocialPost, 'threads', 'mosseri/DCo4WiOvCB0', null, null, null, [], [], false),
        ];
    }

    /**
     * However its address is written, a resource is named as its provider
     * names it, and asked for there: a video by its id and where it starts,
     * a post by its account, as written in lower case, and its id. An
     * address no provider names is read as a page, at its address as kept,
     * without what only tracks who shared it.
     *
     * @param list<string> $asked
     */
    #[DataProvider('addresses')]
    public function testItAsksForTheResourceTheAddressNames(string $url, ResourceKind $kind, ?string $provider, ?string $locator, array $asked): void
    {
        $reading = self::reader()->read(new ResourceUrl($url));

        self::assertSame([$kind, $provider, $locator, false], [$reading->kind, $reading->provider, $reading->locator, $reading->reached]);
        self::assertSame($asked, RecordedSites::asked());
    }

    /**
     * @return iterable<string, array{string, ResourceKind, ?string, ?string, list<string>}>
     */
    public static function addresses(): iterable
    {
        $youtube = static fn (string $url): string => self::oembed('https://www.youtube.com/oembed?format=json&url=', $url);

        yield 'a video of YouTube starting in hours, minutes and seconds' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1h2m3s', ResourceKind::Video, 'youtube', 'dQw4w9WgXcQ@3723', [$youtube('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3723s')]];
        yield 'a short address of YouTube starting in seconds' => ['https://youtu.be/dQw4w9WgXcQ?t=90', ResourceKind::Video, 'youtube', 'dQw4w9WgXcQ@90', [$youtube('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=90s')]];
        yield 'a video of YouTube starting as embeds tell it' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&start=42', ResourceKind::Video, 'youtube', 'dQw4w9WgXcQ@42', [$youtube('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s')]];
        yield 'a video of YouTube starting at its start' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=0', ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, [$youtube(ExternalResourceMother::VIDEO_URL)]];
        yield 'a video of YouTube starting in the last second it takes' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=999999', ResourceKind::Video, 'youtube', 'dQw4w9WgXcQ@999999', [$youtube('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=999999s')]];
        yield 'a video of YouTube starting past the seconds it takes' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1000000', ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, [$youtube(ExternalResourceMother::VIDEO_URL)]];
        yield 'a short of YouTube, upright' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', ResourceKind::Video, 'youtube', 'shorts/dQw4w9WgXcQ', [$youtube('https://www.youtube.com/shorts/dQw4w9WgXcQ')]];
        yield 'a video of YouTube by its embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, [$youtube(ExternalResourceMother::VIDEO_URL)]];
        yield 'a live video of YouTube' => ['https://www.youtube.com/live/dQw4w9WgXcQ', ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, [$youtube(ExternalResourceMother::VIDEO_URL)]];
        yield 'a video of YouTube on its mobile site' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, [$youtube(ExternalResourceMother::VIDEO_URL)]];
        yield 'an address of YouTube whose id is none' => ['https://www.youtube.com/watch?v=corto', ResourceKind::Article, null, null, ['https://www.youtube.com/watch?v=corto']];
        yield 'a post of Bluesky by the handle of its account, in any case' => ['https://bsky.app/profile/Bsky.App/post/3l6oveex3ii2l', ResourceKind::SocialPost, 'bluesky', 'bsky.app/3l6oveex3ii2l', [self::oembed('https://embed.bsky.app/oembed?url=', 'https://bsky.app/profile/bsky.app/post/3l6oveex3ii2l')]];
        yield 'a list of Bluesky, which is no post' => ['https://bsky.app/profile/bsky.app/lists/3l6', ResourceKind::Article, null, null, ['https://bsky.app/profile/bsky.app/lists/3l6']];
        yield 'a post of Mastodon whose server does not answer, read as a page' => ['https://mastodon.social/@Gargron/1', ResourceKind::Article, null, null, ['https://mastodon.social/api/v1/statuses/1', 'https://mastodon.social/@Gargron/1']];
        yield 'an address of Mastodon that is no post' => ['https://mastodon.social/users/Gargron/statuses/1', ResourceKind::Article, null, null, ['https://mastodon.social/users/Gargron/statuses/1']];
        yield 'a post of Threads at its former host' => ['https://www.threads.net/@mosseri/post/DCo4WiOvCB0', ResourceKind::SocialPost, 'threads', 'mosseri/DCo4WiOvCB0', ['https://www.threads.com/@mosseri/post/DCo4WiOvCB0']];
        yield 'a post of X on the mobile site of Twitter' => ['https://mobile.twitter.com/jack/status/20', ResourceKind::SocialPost, 'x', 'jack/20', [self::oembed('https://publish.twitter.com/oembed?omit_script=true&dnt=true&url=', 'https://x.com/jack/status/20')]];
        yield 'a photo of a post of X' => ['https://x.com/jack/status/20/photo/1', ResourceKind::SocialPost, 'x', 'jack/20', [self::oembed('https://publish.twitter.com/oembed?omit_script=true&dnt=true&url=', 'https://x.com/jack/status/20')]];
        yield 'a photo of TikTok, which is no video' => ['https://www.tiktok.com/@scout2015/photo/6718335390845095173', ResourceKind::Article, null, null, ['https://www.tiktok.com/@scout2015/photo/6718335390845095173']];
        yield 'a show of Spotify, tall' => ['https://open.spotify.com/show/2MAi0BvDc6GTFvKFPXnkCL', ResourceKind::Audio, 'spotify', 'show/2MAi0BvDc6GTFvKFPXnkCL', [self::oembed('https://open.spotify.com/oembed?url=', 'https://open.spotify.com/show/2MAi0BvDc6GTFvKFPXnkCL')]];
        yield 'a song of Spotify, which is no episode nor show' => ['https://open.spotify.com/track/1', ResourceKind::Article, null, null, ['https://open.spotify.com/track/1']];
        yield 'a DOI at the old host of its registry' => ['https://dx.doi.org/10.1038/nature12373', ResourceKind::Study, null, null, ['https://api.crossref.org/works/10.1038%2Fnature12373']];
        yield 'an address of doi.org that is no DOI' => ['https://doi.org/11.1/x', ResourceKind::Article, null, null, ['https://doi.org/11.1/x']];
        yield 'an article of PubMed without its last slash' => ['https://pubmed.ncbi.nlm.nih.gov/123', ResourceKind::Study, null, null, ['https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esummary.fcgi?db=pubmed&retmode=json&id=123']];
        yield 'an address of PubMed that is no article' => ['https://pubmed.ncbi.nlm.nih.gov/trending/', ResourceKind::Article, null, null, ['https://pubmed.ncbi.nlm.nih.gov/trending/']];
        yield 'the PDF of a preprint of arXiv, whatever its version' => ['https://arxiv.org/pdf/1706.03762v7.pdf', ResourceKind::Study, null, null, ['https://export.arxiv.org/api/query?id_list=1706.03762']];
        yield 'an address of arXiv that is no preprint' => ['https://arxiv.org/list/cs.AI/recent', ResourceKind::Article, null, null, ['https://arxiv.org/list/cs.AI/recent']];
        yield 'a preprint of arXiv on another host' => ['https://www.arxiv.example/abs/1706.03762', ResourceKind::Article, null, null, ['https://www.arxiv.example/abs/1706.03762']];
        yield 'a video of YouTube by when it starts rather than where it says it starts' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10&start=20', ResourceKind::Video, 'youtube', 'dQw4w9WgXcQ@10', [$youtube('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10s')]];
        yield 'a video of YouTube starting in minutes and seconds' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=2m30s', ResourceKind::Video, 'youtube', 'dQw4w9WgXcQ@150', [$youtube('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=150s')]];
        yield 'a video of YouTube starting in hours' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1h', ResourceKind::Video, 'youtube', 'dQw4w9WgXcQ@3600', [$youtube('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=3600s')]];
        yield 'a video of YouTube starting where it cannot be told' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=pronto', ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, [$youtube(ExternalResourceMother::VIDEO_URL)]];
        yield 'a video of YouTube starting at some seconds and something else' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10x', ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, [$youtube(ExternalResourceMother::VIDEO_URL)]];
        yield 'an address of YouTube below a short' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ/mas', ResourceKind::Article, null, null, ['https://www.youtube.com/shorts/dQw4w9WgXcQ/mas']];
        yield 'an address of YouTube that names no video' => ['https://www.youtube.com/embed', ResourceKind::Article, null, null, ['https://www.youtube.com/embed']];
        yield 'an address written with spaces around' => [' https://www.example.org/a ', ResourceKind::Article, null, null, ['https://www.example.org/a']];
        yield 'an address with parameters that track and others that only look so' => ['https://www.example.org/a?utm_campaign=x&gclid=y&ref_src=z&utmost=1&xsi=2&1=a', ResourceKind::Article, null, null, ['https://www.example.org/a?utmost=1&xsi=2&1=a']];
        yield 'an address over HTTP on the port of HTTPS' => ['http://example.org:443/', ResourceKind::Article, null, null, ['http://example.org:443/']];
        yield 'an address over HTTPS on the port of HTTP' => ['https://example.org:80/', ResourceKind::Article, null, null, ['https://example.org:80/']];
        yield 'a page written in capitals, with its default port and parameters that track' => ['HTTPS://WWW.Example.ORG:443/Noticia.html?utm_source=x&b=2&a=1&fbclid=z#comentarios', ResourceKind::Article, null, null, ['https://www.example.org/Noticia.html?b=2&a=1']];
        yield 'a page over HTTP on its default port' => ['http://example.org:80/', ResourceKind::Article, null, null, ['http://example.org/']];
        yield 'a page on a port of its own' => ['https://example.org:8443/a', ResourceKind::Article, null, null, ['https://example.org:8443/a']];
    }

    /**
     * A resource a provider embeds is checked at its provider, anything else
     * at its own address. Only an answer that tells it is there, or gone,
     * tells its availability, and a provider tells it of what it refuses
     * to show too; a move tells where it went.
     *
     * @param array{status?: int, landed?: string} $answer
     */
    #[DataProvider('checks')]
    public function testItChecksWhetherTheResourceIsStillThere(string $kind, array $answer, CheckOutcome $expected): void
    {
        [$url, $resourceKind, $provider, $locator, $checked] = match ($kind) {
            'video' => [ExternalResourceMother::VIDEO_URL, ResourceKind::Video, 'youtube', ExternalResourceMother::VIDEO, self::oembed('https://www.youtube.com/oembed?format=json&url=', ExternalResourceMother::VIDEO_URL)],
            'study' => [ExternalResourceMother::STUDY_URL, ResourceKind::Study, null, null, ExternalResourceMother::STUDY_URL],
            'unlocated video' => [ExternalResourceMother::VIDEO_URL, ResourceKind::Video, 'youtube', null, ExternalResourceMother::VIDEO_URL],
            default => [ExternalResourceMother::ARTICLE_URL, ResourceKind::Article, null, null, ExternalResourceMother::ARTICLE_URL],
        };

        if (isset($answer['landed'])) {
            RecordedSites::answerRedirected($checked, $answer['landed'], '<html></html>', 'text/html');
        } elseif (isset($answer['status'])) {
            RecordedSites::answer($checked, '{}', $answer['status'], ['content-type' => 'application/json']);
        } else {
            RecordedSites::failToReach($checked);
        }

        self::assertEquals($expected, self::reader()->check(new ResourceUrl($url), $resourceKind, $provider, $locator));
        self::assertSame([$checked], RecordedSites::asked());
    }

    /**
     * @return iterable<string, array{string, array{status?: int, landed?: string}, CheckOutcome}>
     */
    public static function checks(): iterable
    {
        yield 'a video its provider answers for' => ['video', ['status' => 200], CheckOutcome::available(200)];
        yield 'a video its provider refuses to tell' => ['video', ['status' => 400], CheckOutcome::unavailable(400)];
        yield 'a video its provider says is private' => ['video', ['status' => 401], CheckOutcome::unavailable(401)];
        yield 'a video its provider forbids' => ['video', ['status' => 403], CheckOutcome::unavailable(403)];
        yield 'a video its provider no longer has' => ['video', ['status' => 404], CheckOutcome::unavailable(404)];
        yield 'a video whose provider fails' => ['video', ['status' => 500], CheckOutcome::doubtful(500)];
        yield 'a video its provider names not, checked at its address' => ['unlocated video', ['status' => 404], CheckOutcome::unavailable(404)];
        yield 'a page that is gone' => ['article', ['status' => 410], CheckOutcome::unavailable(410)];
        yield 'a page that turns robots away' => ['article', ['status' => 403], CheckOutcome::doubtful(403)];
        yield 'a page that asks who reads it' => ['article', ['status' => 401], CheckOutcome::doubtful(401)];
        yield 'a page that refuses the request' => ['article', ['status' => 400], CheckOutcome::doubtful(400)];
        yield 'a page whose server fails' => ['article', ['status' => 503], CheckOutcome::doubtful(503)];
        yield 'a page whose server never answers' => ['article', [], CheckOutcome::doubtful(null)];
        yield 'a page that moved' => ['article', ['landed' => 'https://www.example.org/2026/noticia.html'], CheckOutcome::moved(200, 'https://www.example.org/2026/noticia.html')];
        yield 'a page that moved to the home of another site' => ['article', ['landed' => 'https://www.otro.example/'], CheckOutcome::moved(200, 'https://www.otro.example/')];
        yield 'a page that only leads home now' => ['article', ['landed' => 'https://www.example.org/'], CheckOutcome::unavailable(200)];
        yield 'a page rewritten only as sites do' => ['article', ['landed' => 'https://example.org/noticia.html/'], CheckOutcome::available(200)];
        yield 'a page that led to itself over HTTP' => ['article', ['landed' => 'http://www.example.org/noticia.html'], CheckOutcome::available(200)];
        yield 'a study, which its registry always takes to its publisher' => ['study', ['landed' => 'https://www.nature.com/articles/nature12373'], CheckOutcome::available(200)];
        yield 'a study its registry no longer has' => ['study', ['status' => 404], CheckOutcome::unavailable(404)];
    }

    private static function reader(): HttpResourceReader
    {
        $http = new RemoteHttp(new MockHttpClient(new RecordedSites()));
        $json = new JsonReading($http);

        return new HttpResourceReader($http, new ProviderReading($json, new PostReading($json, $http)), new StudyReading($http), new PageReading($http));
    }

    private static function oembed(string $endpoint, string $url): string
    {
        return $endpoint.rawurlencode($url);
    }

    /**
     * @param array<string, null|bool|int|string> $details
     */
    private static function post(string $provider, string $locator, string $title, ?Language $language, array $details): Reading
    {
        return new Reading(ResourceKind::SocialPost, $provider, $locator, $title, null, $language, $details, [], true);
    }

    /**
     * @param array<string, null|bool|int|string> $details
     */
    private static function study(string $title, ?string $summary, ?Language $language, array $details): Reading
    {
        return new Reading(ResourceKind::Study, null, null, $title, $summary, $language, $details, [], true);
    }
}
