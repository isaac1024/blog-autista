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
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Tests\Page\Domain\CmsPagePublishedMother;

final class VisitControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;

    private const string HOST = 'http://www.blogautista.localhost';
    private const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e001';
    private const string BROWSER = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';
    private const string PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    public function testItKeepsAVisitToAPageOnTheSite(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, referrer: 'https://www.google.es/', zone: 'Europe/Madrid');

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([[
            'page_id' => self::PAGE,
            'day' => '2026-11-07',
            'channel' => 'search',
            'source' => 'google.es',
            'device' => 'desktop',
            'country' => 'ES',
        ]], $this->visits());
    }

    public function testTheAnswerIsKeptByNoCacheAndSetsNoCookie(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseNotHasHeader('Set-Cookie');
    }

    /**
     * @return iterable<string, array{string, string, string, string, ?string}>
     */
    public static function arrivals(): iterable
    {
        yield 'no page left' => ['', '', '', 'direct', null];
        yield 'a page of the site' => [self::HOST.'/otra.html', '', '', 'internal', null];
        yield 'the CMS' => ['http://cms.blogautista.localhost/pages', '', '', 'internal', null];
        yield 'webmail, though on a search engine' => ['https://mail.google.com/', '', '', 'email', 'mail.google.com'];
        yield 'an AI assistant, though on a search engine' => ['https://gemini.google.com/', '', '', 'ai', 'gemini.google.com'];
        yield 'another AI assistant' => ['https://chatgpt.com/', '', '', 'ai', 'chatgpt.com'];
        yield 'a search engine under a country domain' => ['https://www.google.com.mx/', '', '', 'search', 'google.com.mx'];
        yield 'a search engine under a subdomain' => ['https://search.brave.com/search?q=autismo', '', '', 'search', 'search.brave.com'];
        yield 'a search engine of its own domain' => ['https://duckduckgo.com/', '', '', 'search', 'duckduckgo.com'];
        yield 'not a search engine, though named like one' => ['https://google.example.com/', '', '', 'referral', 'google.example.com'];
        yield 'a social network under a subdomain' => ['https://l.facebook.com/', '', '', 'social', 'l.facebook.com'];
        yield 'a social network under a country domain' => ['https://es.pinterest.com/', '', '', 'social', 'es.pinterest.com'];
        yield 'any other site' => ['https://www.example.org/post', '', '', 'referral', 'example.org'];
        yield 'a campaign medium' => ['', 'boletin', 'newsletter', 'email', 'boletin'];
        yield 'a campaign medium over the page left' => ['https://www.google.com/', '', 'social', 'social', 'google.com'];
        yield 'a known campaign source' => ['', 'IG', '', 'social', 'ig'];
        yield 'an AI assistant as campaign source' => ['', 'chatgpt.com', '', 'ai', 'chatgpt.com'];
        yield 'an unknown campaign source with no page left' => ['', 'foro-amigo', '', 'referral', 'foro-amigo'];
        yield 'an unknown campaign source from a search engine' => ['https://www.bing.com/', 'foro-amigo', '', 'search', 'foro-amigo'];
        yield 'a campaign source the blog cannot keep' => ['', 'Mi campaña', '', 'direct', null];
        yield 'an address' => ['https://192.168.1.20/', '', '', 'referral', null];
        yield 'an IPv6 address' => ['http://[2001:db8::1]/', '', '', 'referral', null];
        yield 'no domain' => ['http://localhost:3000/', '', '', 'referral', null];
        yield 'no web address' => ['android-app://com.google.android.gm', '', '', 'direct', null];
        yield 'no address at all' => ['otra página', '', '', 'direct', null];
        yield 'a page left written loosely' => ['  HTTPS://WWW.Google.ES/  ', '', '', 'search', 'google.es'];
        yield 'a campaign written loosely' => ['', '  IG  ', '  NewsLetter  ', 'email', 'ig'];
        yield 'a domain that only ends like a known one' => ['https://netflix.com/', '', '', 'referral', 'netflix.com'];
        yield 'a host too long to be a domain' => ['https://'.str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 58).'.org/', '', '', 'referral', null];
        yield 'the longest host a domain can be' => ['https://'.str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.org/', '', '', 'referral', str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.org'];
        yield 'a host that begins as no domain does' => ['https://-foro.example.org/', '', '', 'referral', null];
        yield 'a host that ends as no domain does' => ['https://example.org_x/', '', '', 'referral', null];
    }

    #[DataProvider('arrivals')]
    public function testItTellsWhereTheReaderCameFrom(string $referrer, string $source, string $medium, string $channel, ?string $expectedSource): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, referrer: $referrer, source: $source, medium: $medium);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([$channel, $expectedSource], [$this->visits()[0]['channel'], $this->visits()[0]['source']]);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function timeZones(): iterable
    {
        yield 'a zone of a country' => ['America/Mexico_City', 'MX'];
        yield 'a zone of the islands' => ['Atlantic/Canary', 'ES'];
        yield 'an old name Chrome gives' => ['America/Buenos_Aires', 'AR'];
        yield 'another old name' => ['Asia/Calcutta', 'IN'];
        yield 'universal time' => ['UTC', null];
        yield 'no zone PHP knows' => ['Marte/Olympus', null];
        yield 'none' => ['', null];
    }

    #[DataProvider('timeZones')]
    public function testItTellsTheCountryByTheTimeZoneOfTheBrowser(string $zone, ?string $country): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, zone: $zone);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame($country, $this->visits()[0]['country']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function devices(): iterable
    {
        yield 'a computer' => [self::BROWSER, 'desktop'];
        yield 'a phone' => [self::PHONE, 'mobile'];
        yield 'an Android tablet' => ['Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'mobile'];
    }

    #[DataProvider('devices')]
    public function testItTellsThePhoneFromTheComputer(string $userAgent, string $device): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, userAgent: $userAgent);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame($device, $this->visits()[0]['device']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function robots(): iterable
    {
        yield 'a search engine' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'];
        yield 'a headless browser' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/136.0.0.0 Safari/537.36'];
        yield 'a link preview' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'];
        yield 'a script' => ['python-requests/2.32.3'];
        yield 'no name' => [''];
        yield 'blanks for a name' => ['   '];
    }

    #[DataProvider('robots')]
    public function testItCountsNoRobot(string $userAgent): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, userAgent: $userAgent);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertSame([], $this->visits());
    }

    /**
     * The same browser at the same address is one reader all day; another
     * browser, or another network, is another reader.
     */
    public function testItTellsOneReaderOfTheDayFromAnother(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, address: '203.0.113.7');
        $this->visit($client, address: '203.0.113.7');
        $this->visit($client, address: '203.0.113.7', userAgent: self::PHONE);
        $this->visit($client, address: '203.0.113.8');
        $this->visit($client, address: '2001:db8:1:2:aaaa::1');
        $this->visit($client, address: '2001:db8:1:2:bbbb::2');
        $this->visit($client, address: '2001:db8:1:3::1');
        $this->visit($client, address: '::ffff:203.0.113.8');

        $readers = $this->readers();
        self::assertCount(8, $readers);
        self::assertSame($readers[0], $readers[1], 'The same browser at the same address');
        self::assertNotSame($readers[0], $readers[2], 'Another browser');
        self::assertNotSame($readers[0], $readers[3], 'Another address');
        self::assertSame($readers[4], $readers[5], 'Another address of the same IPv6 network');
        self::assertNotSame($readers[4], $readers[6], 'Another IPv6 network');
        self::assertSame($readers[3], $readers[7], 'An IPv4 address written as IPv6');
        self::assertNotContains('203.0.113.7', $readers);
    }

    /**
     * The salt changes with the day, so a reader of one day can't be told
     * as the one of another.
     */
    public function testAReaderOfOneDayIsNotTheReaderOfAnother(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, address: '203.0.113.7');
        self::mockTime('2026-11-08 10:00:00');
        $this->visit($client, address: '203.0.113.7');

        $readers = $this->readers();
        self::assertCount(2, $readers);
        self::assertNotSame($readers[0], $readers[1]);
    }

    /**
     * The day is Madrid's: 23:30 in universal time is already the next day there.
     */
    public function testItCountsTheVisitOnTheDayOfMadrid(): void
    {
        $client = $this->clientWithPage();
        self::mockTime('2026-11-07 23:30:00 UTC');

        $this->visit($client);

        self::assertSame('2026-11-08', $this->visits()[0]['day']);
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
        yield 'another host of the same site' => [['HTTP_SEC_FETCH_SITE' => 'same-site']];
        yield 'another origin' => [['HTTP_ORIGIN' => 'https://example.org']];
        yield 'no word of where it comes from' => [[]];
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
        $visit = ['page' => self::PAGE, 'referrer' => '', 'source' => '', 'medium' => '', 'zone' => ''];

        yield 'no page id' => [['page' => 'sobre-mi'] + $visit];
        yield 'a field missing' => [array_diff_key($visit, ['zone' => true])];
        yield 'a page left too long' => [['referrer' => 'https://example.org/'.str_repeat('a', 2029)] + $visit];
        yield 'a campaign source too long' => [['source' => str_repeat('a', 201)] + $visit];
        yield 'a campaign medium too long' => [['medium' => str_repeat('a', 201)] + $visit];
        yield 'a time zone too long' => [['zone' => str_repeat('a', 65)] + $visit];
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

    public function testItTakesTheLongestVisitItAllows(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, referrer: 'https://example.org/'.str_repeat('a', 2028), source: str_repeat('a', 200), medium: str_repeat('a', 200), zone: str_repeat('a', 64));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertCount(1, $this->visits());
    }

    /**
     * Lengths are counted in characters, however many bytes each takes.
     */
    public function testItCountsTheLengthsInCharacters(): void
    {
        $client = $this->clientWithPage();

        $this->visit($client, referrer: 'https://example.org/'.str_repeat('ñ', 2028), zone: str_repeat('ñ', 64));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertCount(1, $this->visits());
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
     * @param array<string, string> $headers
     */
    private function visit(
        KernelBrowser $client,
        string $page = self::PAGE,
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
            ['page' => $page, 'referrer' => $referrer, 'source' => $source, 'medium' => $medium, 'zone' => $zone],
            server: ['HTTP_USER_AGENT' => $userAgent, 'REMOTE_ADDR' => $address] + $headers,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visits(): array
    {
        return self::getContainer()->get(Connection::class)->fetchAllAssociative('SELECT page_id, day, channel, source, device, country FROM web_visit ORDER BY id');
    }

    /**
     * @return list<mixed>
     */
    private function readers(): array
    {
        return self::getContainer()->get(Connection::class)->fetchFirstColumn('SELECT reader FROM web_visit ORDER BY id');
    }
}
