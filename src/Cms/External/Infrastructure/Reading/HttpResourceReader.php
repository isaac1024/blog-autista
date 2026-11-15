<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Reading;

use Cms\External\Domain\CheckOutcome;
use Cms\External\Domain\Reading;
use Cms\External\Domain\ResourceReader;
use Cms\External\Domain\ResourceSource;
use Cms\External\Domain\ResourceUrl;
use Cms\External\Domain\StudyReference;
use Shared\Domain\External\Providers;
use Shared\Domain\External\ResourceKind;
use Shared\Domain\External\WebAddress;
use Shared\Infrastructure\Http\RemoteHttp;
use Shared\Infrastructure\Http\RemoteResponse;
use Shared\Infrastructure\Http\RemoteUnreachable;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that reads external resources over HTTP: from their
 * provider, when one embeds them, from the registry of a study, or from
 * their own page.
 *
 * Checking tells a resource gone from one that can't be told, so only an
 * answer that says it is gone, or no longer public, makes it unavailable:
 * 404 and 410 for any; 401 and 403 too from a provider, which answers that
 * way for a private post or video, but not from any other site, many of
 * which turn robots away so.
 */
#[AsAlias(ResourceReader::class)]
final readonly class HttpResourceReader implements ResourceReader
{
    private const int CHECK_BYTES = 65_536;

    public function __construct(
        private RemoteHttp $http,
        private ProviderReading $providerReading,
        private StudyReading $studyReading,
        private PageReading $pageReading,
    ) {}

    #[\Override]
    public function read(ResourceUrl $url): Reading
    {
        $source = ResourceSource::fromUrl($url, new Providers());
        $provider = $source->provider();
        $locator = $source->locator();

        if (null !== $provider && null !== $locator) {
            $reading = $this->providerReading->read($provider, $locator);

            if (null !== $reading) {
                return $reading;
            }

            // A server that doesn't answer as Mastodon isn't one: the address
            // is that of a page, whatever it looks like.
            if ('mastodon' !== $provider->name()) {
                return new Reading($provider->kind(), $provider->name(), $locator, null, null, null, [], [], false);
            }
        }

        $study = StudyReference::fromAddress($url->address());

        if (null !== $study) {
            return $this->studyReading->read($study) ?? new Reading(ResourceKind::Study, null, null, null, null, null, [], [], false);
        }

        return $this->pageReading->read($url);
    }

    #[\Override]
    public function check(ResourceUrl $url, ResourceKind $kind, ?string $provider, ?string $locator): CheckOutcome
    {
        if (null !== $provider && null !== $locator) {
            return $this->outcome(ProviderEndpoints::forResource(new Providers()->named($provider), $locator), true, null);
        }

        // A study is reached through its registry, which always takes readers
        // to the page of its publisher: that is where it is.
        return $this->outcome($url->value(), false, ResourceKind::Study === $kind ? null : $url->address());
    }

    /**
     * @param bool        $fromProvider whether the provider answers, which tells a private resource by 401 or 403
     * @param ?WebAddress $address      the address asked for, to tell where a redirect led
     */
    private function outcome(string $url, bool $fromProvider, ?WebAddress $address): CheckOutcome
    {
        try {
            $response = $this->http->get($url, self::CHECK_BYTES, '*/*');
        } catch (RemoteUnreachable) {
            return CheckOutcome::doubtful(null);
        }

        $status = $response->status;

        if (\in_array($status, [404, 410], true) || ($fromProvider && \in_array($status, [400, 401, 403], true))) {
            return CheckOutcome::unavailable($status);
        }

        if (!$response->isSuccessful()) {
            return CheckOutcome::doubtful($status);
        }

        return null === $address ? CheckOutcome::available($status) : self::landed($response, $address);
    }

    /**
     * Where a page that answered landed: on itself, or on what it moved to.
     * A page that only takes readers to the home page of its site now is
     * gone, as a site does with the pieces it removes.
     */
    private static function landed(RemoteResponse $response, WebAddress $address): CheckOutcome
    {
        $landed = WebAddress::fromString($response->url);

        if (!$response->redirected || null === $landed || self::same($landed, $address)) {
            return CheckOutcome::available($response->status);
        }

        if ([] === $landed->segments() && [] !== $address->segments() && $landed->host() === $address->host()) {
            return CheckOutcome::unavailable($response->status);
        }

        return CheckOutcome::moved($response->status, $landed->normalized());
    }

    /**
     * Whether two addresses are the same page, written apart only in how
     * sites rewrite their own: HTTP or HTTPS, with "www." or not, with a
     * slash at the end or not.
     */
    private static function same(WebAddress $one, WebAddress $other): bool
    {
        $page = static fn (WebAddress $address): string => (string) preg_replace('#^https?://(www\.)?#', '', rtrim($address->normalized(), '/'));

        return $page($one) === $page($other);
    }
}
