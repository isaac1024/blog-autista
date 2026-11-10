<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Web\Analytics\Application\VisitRecorder;
use Web\Analytics\Domain\Channel;
use Web\Analytics\Domain\DailySalt;
use Web\Analytics\Domain\Day;
use Web\Analytics\Domain\Device;
use Web\Analytics\Domain\PublishedPages;
use Web\Analytics\Domain\Visit;
use Web\Analytics\Domain\VisitRepository;
use Web\Tests\Analytics\Domain\VisitMother;

/**
 * What a visit records: where its reader came from, the country of its
 * browser, its device and the reader of the day it is, on the day of
 * Madrid; robots go uncounted, and what it can't take is told.
 */
final class VisitRecorderTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string HOST = 'http://www.blogautista.localhost';
    private const string PAGE = VisitMother::PAGE;
    private const string BROWSER = VisitMother::BROWSER;
    private const string PHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
    private const string OTHER_BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    /**
     * The reader is kept as a hash of the network of the address and the
     * browser, keyed by the salt of the day: never the address itself.
     */
    public function testItRecordsTheVisitOfAPageOnTheSite(): void
    {
        self::assertEquals(
            VisitMother::create(channel: Channel::Search, source: 'google.es', reader: VisitMother::READER, country: 'ES'),
            $this->record(referrer: 'https://www.google.es/', zone: 'Europe/Madrid'),
        );
    }

    public function testItTellsThePageIsOffTheSite(): void
    {
        self::assertTells('errors.visit.page_not_published', ['%id%' => self::PAGE], fn () => $this->record(published: false));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, int}>
     */
    public static function tooLong(): iterable
    {
        yield 'the page left' => [['referrer' => 'https://example.org/'.str_repeat('a', 2029)], 'referrer', 2048];
        yield 'the campaign source' => [['source' => str_repeat('a', 201)], 'campaign source', 200];
        yield 'the campaign medium' => [['medium' => str_repeat('a', 201)], 'campaign medium', 200];
        yield 'the time zone' => [['zone' => str_repeat('a', 65)], 'time zone', 64];
    }

    /**
     * @param array<string, string> $visit
     */
    #[DataProvider('tooLong')]
    public function testItTellsWhatOfTheVisitIsTooLong(array $visit, string $field, int $max): void
    {
        self::assertTells('errors.visit.too_long', ['%field%' => $field, '%max%' => $max], fn () => $this->record(...$visit));
    }

    /**
     * As long as each can be, counted in characters, however many bytes
     * each takes, a visit is recorded.
     */
    public function testItRecordsTheLongestVisitItAllows(): void
    {
        self::assertNotNull($this->record(referrer: 'https://example.org/'.str_repeat('a', 2028), source: str_repeat('a', 200), medium: str_repeat('a', 200), zone: str_repeat('a', 64)));
        self::assertNotNull($this->record(referrer: 'https://example.org/'.str_repeat('ñ', 2028), zone: str_repeat('ñ', 64)));
    }

    /**
     * An address the request can't tell is taken as it comes.
     */
    public function testItKeepsAVisitFromAnAddressItCannotRead(): void
    {
        self::assertEquals(VisitMother::create(reader: VisitMother::reader(address: 'unknown')), $this->record(address: 'unknown'));
    }

    /**
     * The page is kept as its id is written on the site.
     */
    public function testItKeepsThePageAsItsIdIsWrittenOnTheSite(): void
    {
        self::assertEquals(VisitMother::create(), $this->record(page: strtoupper(self::PAGE)));
    }

    /**
     * @return iterable<string, array{string, string, string, string, ?string}>
     */
    public static function arrivals(): iterable
    {
        yield 'no page left' => ['', '', '', 'direct', null];
        yield 'a page of the site' => [self::HOST.'/otra.html', '', '', 'internal', null];
        yield 'the CMS' => ['http://cms.blogautista.localhost/pages', '', '', 'internal', null];
        yield 'a page of the site, though its link names a campaign' => [self::HOST.'/otra.html', 'instagram', 'social', 'internal', null];
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function devices(): iterable
    {
        yield 'a computer' => [self::BROWSER, 'desktop'];
        yield 'a phone' => [self::PHONE, 'mobile'];
        yield 'an Android tablet' => ['Mozilla/5.0 (Linux; Android 14; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', 'mobile'];
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

    #[DataProvider('arrivals')]
    public function testItTellsWhereTheReaderCameFrom(string $referrer, string $source, string $medium, string $channel, ?string $expectedSource): void
    {
        self::assertEquals(
            VisitMother::create(channel: Channel::from($channel), source: $expectedSource),
            $this->record(referrer: $referrer, source: $source, medium: $medium),
        );
    }

    #[DataProvider('timeZones')]
    public function testItTellsTheCountryByTheTimeZoneOfTheBrowser(string $zone, ?string $country): void
    {
        self::assertEquals(VisitMother::create(country: $country), $this->record(zone: $zone));
    }

    #[DataProvider('devices')]
    public function testItTellsThePhoneFromTheComputer(string $userAgent, string $device): void
    {
        self::assertEquals(
            VisitMother::create(reader: VisitMother::reader(userAgent: $userAgent), device: Device::from($device)),
            $this->record(userAgent: $userAgent),
        );
    }

    #[DataProvider('robots')]
    public function testItCountsNoRobot(string $userAgent): void
    {
        self::assertNull($this->record(userAgent: $userAgent));
    }

    /**
     * The same browser at the same address is one reader all day; another
     * browser, or another network, is another reader. Visits recorded alike
     * but for their reader tell them apart.
     */
    public function testItTellsOneReaderOfTheDayFromAnother(): void
    {
        $visit = fn (string $address, string $userAgent = self::BROWSER): ?Visit => $this->record(address: $address, userAgent: $userAgent);

        self::assertEquals($visit('203.0.113.7'), $visit('203.0.113.7'), 'The same browser at the same address');
        self::assertNotEquals($visit('203.0.113.7'), $visit('203.0.113.7', self::OTHER_BROWSER), 'Another browser');
        self::assertNotEquals($visit('203.0.113.7'), $visit('203.0.113.8'), 'Another address');
        self::assertEquals($visit('2001:db8:1:2:aaaa::1'), $visit('2001:db8:1:2:bbbb::2'), 'Another address of the same IPv6 network');
        self::assertNotEquals($visit('2001:db8:1:2:aaaa::1'), $visit('2001:db8:1:3::1'), 'Another IPv6 network');
        self::assertEquals($visit('203.0.113.8'), $visit('::ffff:203.0.113.8'), 'An IPv4 address written as IPv6');
    }

    /**
     * The salt changes with the day, so a reader of one day can't be told
     * as the one of another.
     */
    public function testAReaderOfOneDayIsNotTheReaderOfAnother(): void
    {
        self::mockTime('2026-11-08 10:00:00');

        self::assertEquals(VisitMother::create(day: '2026-11-08', reader: VisitMother::reader(day: '2026-11-08')), $this->record());
        self::assertNotSame(VisitMother::READER, VisitMother::reader(day: '2026-11-08'));
    }

    /**
     * The day is Madrid's: 23:30 in universal time is already the next day there.
     */
    public function testItRecordsTheVisitOnTheDayOfMadrid(): void
    {
        self::mockTime('2026-11-07 23:30:00 UTC');

        self::assertEquals(VisitMother::create(day: '2026-11-08', reader: VisitMother::reader(day: '2026-11-08')), $this->record());
    }

    /**
     * The visit the recorder saves, by the id the tests give it, with a salt
     * of its own for each day; null when it saves none.
     */
    private function record(
        string $page = self::PAGE,
        string $referrer = '',
        string $source = '',
        string $medium = '',
        string $zone = '',
        string $address = VisitMother::ADDRESS,
        string $userAgent = self::BROWSER,
        bool $published = true,
    ): ?Visit {
        $publishedPages = self::createStub(PublishedPages::class);
        $publishedPages->method('contains')->willReturn($published);
        $dailySalt = self::createStub(DailySalt::class);
        $dailySalt->method('forDay')->willReturnCallback(VisitMother::salt(...));
        $saved = null;
        $visitRepository = $this->createMock(VisitRepository::class);
        $visitRepository->expects(self::atMost(1))->method('save')->willReturnCallback(static function (Visit $visit) use (&$saved): void {
            $saved = $visit;
        });

        new VisitRecorder(VisitMother::ID, $page, $referrer, $source, $medium, $zone, $address, $userAgent, ['www.blogautista.localhost', 'cms.blogautista.localhost'])(
            $visitRepository,
            $publishedPages,
            $dailySalt,
        );

        return $saved;
    }
}
