<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * The country a reader is in, as the time zone of their browser tells it:
 * no address is looked up. Unknown when the browser keeps universal time or
 * names no zone of a country.
 */
final readonly class Country
{
    public const int MAX_TIME_ZONE_LENGTH = 64;

    private const string NO_COUNTRY = '??';

    /**
     * Old names Chrome still gives some zones, which PHP knows by no country.
     */
    private const array ALIASES = [
        'Africa/Asmera' => 'Africa/Asmara',
        'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
        'America/Catamarca' => 'America/Argentina/Catamarca',
        'America/Cordoba' => 'America/Argentina/Cordoba',
        'America/Godthab' => 'America/Nuuk',
        'America/Indianapolis' => 'America/Indiana/Indianapolis',
        'America/Jujuy' => 'America/Argentina/Jujuy',
        'America/Louisville' => 'America/Kentucky/Louisville',
        'America/Mendoza' => 'America/Argentina/Mendoza',
        'Asia/Calcutta' => 'Asia/Kolkata',
        'Asia/Katmandu' => 'Asia/Kathmandu',
        'Asia/Rangoon' => 'Asia/Yangon',
        'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
        'Atlantic/Faeroe' => 'Atlantic/Faroe',
        'Europe/Kiev' => 'Europe/Kyiv',
        'Pacific/Enderbury' => 'Pacific/Kanton',
        'Pacific/Ponape' => 'Pacific/Pohnpei',
        'Pacific/Truk' => 'Pacific/Chuuk',
    ];

    private function __construct(private ?string $code) {}

    public static function ofTimeZone(string $timeZone): self
    {
        if (mb_strlen($timeZone) > self::MAX_TIME_ZONE_LENGTH) {
            throw InvalidVisit::tooLong('time zone', self::MAX_TIME_ZONE_LENGTH);
        }

        try {
            $location = new \DateTimeZone(self::ALIASES[$timeZone] ?? $timeZone)->getLocation();
        } catch (\Exception) {
            // No zone PHP knows.
            return new self(null);
        }

        // PHP tells a zone of no country, such as UTC, by "??".
        $code = false === $location ? self::NO_COUNTRY : $location['country_code'];

        return new self(self::NO_COUNTRY === $code ? null : $code);
    }

    /**
     * The ISO 3166 code of the country, or null when unknown.
     */
    public function code(): ?string
    {
        return $this->code;
    }
}
