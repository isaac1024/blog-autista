<?php

declare(strict_types=1);

namespace Web\Tests\Analytics\Domain;

use Web\Analytics\Domain\Channel;
use Web\Analytics\Domain\Country;
use Web\Analytics\Domain\Day;
use Web\Analytics\Domain\Device;
use Web\Analytics\Domain\Reader;
use Web\Analytics\Domain\UserAgent;
use Web\Analytics\Domain\Visit;

/**
 * A visit of a reader arriving straight from nowhere, on a computer of no
 * country it tells, on the day of the clock of the tests.
 */
final class VisitMother
{
    public const string ID = VisitIdMother::DEFAULT_VALUE;
    public const string PAGE = '01a1b801-ce58-723d-8234-b76d9105e201';
    public const string DAY = '2026-11-07';
    public const string ADDRESS = '203.0.113.1';
    public const string BROWSER = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';

    /**
     * The reader of the browser at the address on the day, by the salt
     * salt() gives that day.
     */
    public const string READER = '4ef24a3f9c6202b3073b60e33150cc2cf32e53022577fc78b8b1a0abc21a5103';

    public static function create(
        string $id = self::ID,
        string $page = self::PAGE,
        string $day = self::DAY,
        Channel $channel = Channel::Direct,
        ?string $source = null,
        string $reader = self::READER,
        Device $device = Device::Desktop,
        ?string $country = null,
    ): Visit {
        return new Visit(VisitIdMother::create($id), $page, new \DateTimeImmutable($day), $channel, $source, $reader, $device, $country);
    }

    /**
     * The salt the tests give each day: one of its own.
     */
    public static function salt(Day $day): string
    {
        return 'salt-'.$day->value();
    }

    /**
     * The reader of a browser at an address on a day, as the visit tells it.
     */
    public static function reader(string $address = self::ADDRESS, string $userAgent = self::BROWSER, string $day = self::DAY): string
    {
        return Reader::from('salt-'.$day, $address, new UserAgent($userAgent), Country::ofTimeZone(''))->hash();
    }
}
