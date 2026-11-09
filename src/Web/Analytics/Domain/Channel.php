<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

/**
 * How a reader came to a page: from outside the site, through one of these,
 * or from another page of it.
 */
enum Channel: string
{
    case Direct = 'direct';
    case Internal = 'internal';
    case Search = 'search';
    case Social = 'social';
    case Email = 'email';
    case Ai = 'ai';
    case Referral = 'referral';

    /**
     * Webmail, read before search engines: its hosts are those of one.
     */
    private const array WEBMAIL_HOSTS = ['mail.google.com', 'outlook.live.com', 'outlook.office.com', 'outlook.office365.com', 'mail.yahoo.com'];

    /**
     * AI assistants, read before search engines: Gemini is on google.com.
     */
    private const array AI_HOSTS = ['chatgpt.com', 'chat.openai.com', 'perplexity.ai', 'gemini.google.com', 'copilot.microsoft.com', 'claude.ai'];

    private const array SEARCH_HOSTS = ['bing.com', 'duckduckgo.com', 'search.brave.com', 'ecosia.org', 'startpage.com', 'qwant.com', 'baidu.com'];

    /**
     * Search engines found under a country domain of their own, such as google.es.
     */
    private const array SEARCH_BRANDS = ['google', 'yahoo', 'yandex'];

    private const array SOCIAL_HOSTS = [
        'facebook.com', 'instagram.com', 't.co', 'x.com', 'twitter.com', 'linkedin.com', 'lnkd.in', 'reddit.com',
        'bsky.app', 'threads.net', 'threads.com', 'youtube.com', 'tiktok.com', 't.me', 'wa.me', 'whatsapp.com', 'mastodon.social',
    ];

    private const array SOCIAL_BRANDS = ['pinterest'];

    /**
     * The names campaigns give the places their links are shared at.
     */
    private const array SOURCE_NAMES = [
        'facebook' => self::Social, 'fb' => self::Social, 'instagram' => self::Social, 'ig' => self::Social,
        'twitter' => self::Social, 'x' => self::Social, 'linkedin' => self::Social, 'reddit' => self::Social,
        'bluesky' => self::Social, 'bsky' => self::Social, 'threads' => self::Social, 'mastodon' => self::Social,
        'youtube' => self::Social, 'tiktok' => self::Social, 'pinterest' => self::Social, 'telegram' => self::Social,
        'whatsapp' => self::Social,
        'chatgpt' => self::Ai, 'chatgpt.com' => self::Ai, 'openai' => self::Ai, 'perplexity' => self::Ai,
        'gemini' => self::Ai, 'copilot' => self::Ai, 'claude' => self::Ai,
        'newsletter' => self::Email, 'email' => self::Email,
    ];

    private const array EMAIL_MEDIUMS = ['email', 'e-mail', 'newsletter'];

    private const array SOCIAL_MEDIUMS = ['social', 'social-media', 'social_media'];

    /**
     * The channel a campaign medium names, if it names one.
     */
    public static function ofMedium(string $medium): ?self
    {
        return match (true) {
            \in_array($medium, self::EMAIL_MEDIUMS, true) => self::Email,
            \in_array($medium, self::SOCIAL_MEDIUMS, true) => self::Social,
            default => null,
        };
    }

    /**
     * The channel a campaign source names, if it is one the blog knows.
     */
    public static function ofSourceName(string $source): ?self
    {
        return self::SOURCE_NAMES[$source] ?? null;
    }

    /**
     * The channel of a host off the site: referral for any the blog doesn't know.
     */
    public static function ofHost(string $host): self
    {
        return match (true) {
            \in_array($host, self::WEBMAIL_HOSTS, true) => self::Email,
            self::isUnder($host, self::AI_HOSTS) => self::Ai,
            self::isUnder($host, self::SEARCH_HOSTS), self::isOfBrand($host, self::SEARCH_BRANDS) => self::Search,
            self::isUnder($host, self::SOCIAL_HOSTS), self::isOfBrand($host, self::SOCIAL_BRANDS) => self::Social,
            default => self::Referral,
        };
    }

    /**
     * Whether a reader entered the site through this channel, rather than
     * moving within it.
     */
    public function isEntry(): bool
    {
        return self::Internal !== $this;
    }

    /**
     * @param list<string> $domains
     */
    private static function isUnder(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the host is the brand's under any country domain: google.es,
     * www.google.com.mx, but not google.example.com.
     *
     * @param list<string> $brands
     */
    private static function isOfBrand(string $host, array $brands): bool
    {
        foreach ($brands as $brand) {
            if (1 === preg_match('/(?:^|\.)'.$brand.'\.(?:com?\.)?[a-z]{2,3}$/', $host)) {
                return true;
            }
        }

        return false;
    }
}
