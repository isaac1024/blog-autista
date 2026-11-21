<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

use Psr\Log\LoggerInterface;
use Shared\Domain\PageCache;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Outbound adapter that forgets a page of the site in Hetzner's Varnish with
 * a PURGE of its address, which Varnish only takes from the server of the
 * account, where the cron that applies what the CMS publishes runs.
 *
 * Only where VARNISH_PURGE is on: development and the tests have no Varnish.
 * A purge that fails is a warning of the log, which app:health tells the
 * next day, and nothing else: the page expires by itself all the same.
 */
#[AsAlias(PageCache::class)]
final readonly class VarnishPageCache implements PageCache
{
    private const float TIMEOUT = 5.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire(env: 'bool:VARNISH_PURGE')]
        private bool $enabled,
        #[Autowire(env: 'WEB_HOST')]
        private string $webHost,
    ) {}

    #[\Override]
    public function forget(string $path): void
    {
        if (!$this->enabled) {
            return;
        }

        $url = \sprintf('https://%s%s', $this->webHost, $path);

        try {
            $status = $this->httpClient->request('PURGE', $url, ['timeout' => self::TIMEOUT, 'max_redirects' => 0])->getStatusCode();
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('The page cache could not forget {url}: {reason}', ['url' => $url, 'reason' => $exception->getMessage()]);

            return;
        }

        if (200 !== $status) {
            $this->logger->warning('The page cache refused to forget {url}: status {status}', ['url' => $url, 'status' => $status]);
        }
    }
}
