<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * Where a reader came to a page from: the channel and, for one from outside
 * the site, the source that sent them, which is the campaign's name or the
 * domain of the page they left.
 *
 * A page of the site left is no entry, whatever campaign its link names: the
 * reader was already on the site. Else a campaign's medium or known source
 * says the channel, and failing those the address of the page they left
 * does. A campaign of an unknown source with no such page still came from
 * somewhere, so it is a referral rather than direct.
 */
final readonly class Arrival
{
    public const int MAX_REFERRER_LENGTH = 2048;

    public const int MAX_CAMPAIGN_LENGTH = 200;

    public const int MAX_SOURCE_LENGTH = 253;

    private function __construct(
        private Channel $channel,
        private ?string $source,
    ) {}

    /**
     * @param string       $referrer       the address of the page the reader left, empty for none
     * @param string       $campaignSource utm_source, empty for none
     * @param string       $campaignMedium utm_medium, empty for none
     * @param list<string> $siteHosts      the hosts of the blog, whose pages are within the site
     */
    public static function from(string $referrer, string $campaignSource, string $campaignMedium, array $siteHosts): self
    {
        self::assertLength('referrer', $referrer, self::MAX_REFERRER_LENGTH);
        self::assertLength('campaign source', $campaignSource, self::MAX_CAMPAIGN_LENGTH);
        self::assertLength('campaign medium', $campaignMedium, self::MAX_CAMPAIGN_LENGTH);

        $source = self::campaignSource($campaignSource);
        $host = self::host($referrer);
        $channel = self::channelWithin($host, $siteHosts)
            ?? Channel::ofMedium(strtolower(trim($campaignMedium)))
            ?? (null === $source ? null : Channel::ofSourceName($source))
            ?? (null === $host ? null : Channel::ofHost($host))
            ?? (null === $source ? Channel::Direct : Channel::Referral);

        if (!$channel->isEntry()) {
            return new self($channel, null);
        }

        // Direct has neither campaign nor page left, so no source either.
        return new self($channel, $source ?? (null === $host ? null : self::domain($host)));
    }

    public function channel(): Channel
    {
        return $this->channel;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    private static function assertLength(string $field, string $value, int $max): void
    {
        if (mb_strlen($value) > $max) {
            throw InvalidVisit::tooLong($field, $max);
        }
    }

    /**
     * A campaign's source as the blog keeps it, or none for one it can't.
     */
    private static function campaignSource(string $source): ?string
    {
        $source = strtolower(trim($source));

        return 1 === preg_match('/^[a-z0-9._-]{1,50}$/', $source) ? $source : null;
    }

    /**
     * The host of the page left, or none when there is none or it is no web address.
     */
    private static function host(string $referrer): ?string
    {
        $parts = parse_url(trim($referrer));

        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host']) || !\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return strtolower($parts['host']);
    }

    /**
     * Internal for a page of the site left, none for any other.
     *
     * @param list<string> $siteHosts
     */
    private static function channelWithin(?string $host, array $siteHosts): ?Channel
    {
        return null !== $host && \in_array($host, $siteHosts, true) ? Channel::Internal : null;
    }

    /**
     * The domain a host is kept as, without "www.", or none for an address,
     * a name of no domain, or one too long to be real.
     */
    private static function domain(string $host): ?string
    {
        $domain = preg_replace('/^www\./', '', $host) ?? $host;

        if (\strlen($domain) > self::MAX_SOURCE_LENGTH
            || false !== filter_var($domain, \FILTER_VALIDATE_IP)
            || 1 !== preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/', $domain)) {
            return null;
        }

        return $domain;
    }
}
