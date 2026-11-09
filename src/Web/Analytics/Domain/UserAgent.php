<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * What the browser says it is: enough to tell a robot, which is never
 * counted, and a phone from a computer.
 */
final readonly class UserAgent
{
    /**
     * Robots that run scripts say so; a browser without a name is one too.
     */
    private const string ROBOTS = '/bot|crawl|spider|slurp|headless|preview|facebookexternalhit|embedly|python|curl|wget|httpclient|go-http|java\/|okhttp|axios|node-fetch|phantomjs|puppeteer|playwright|selenium/i';

    /**
     * Phones and Android tablets, as MDN suggests reading them.
     */
    private const string MOBILE = '/Mobi|Android/i';

    public function __construct(private string $value) {}

    public function isRobot(): bool
    {
        return '' === trim($this->value) || 1 === preg_match(self::ROBOTS, $this->value);
    }

    public function device(): Device
    {
        return 1 === preg_match(self::MOBILE, $this->value) ? Device::Mobile : Device::Desktop;
    }

    public function value(): string
    {
        return $this->value;
    }
}
