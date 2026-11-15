<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads addresses of other sites. Wraps symfony/http-client, so contexts
 * never depend on it directly.
 *
 * It only reaches the public network, however an address, or a redirect,
 * names a host, through the client configured for it, so nothing pasted makes the server read itself or its
 * neighbours. It waits a short while, follows a few redirects, and reads no
 * more than it is told to. It names itself as a generic reader, telling
 * nothing of the site it reads for.
 */
final readonly class RemoteHttp
{
    private const string USER_AGENT = 'Mozilla/5.0 (compatible; LinkPreview/1.0)';

    public function __construct(
        #[Autowire(service: 'shared.public_network_client')]
        private HttpClientInterface $client,
    ) {}

    /**
     * What the address answers, its body cut once it is longer than given.
     *
     * @param int    $maxBytes how much of the body to read at most
     * @param string $accept   what the answer is asked to be, as an Accept header
     *
     * @throws RemoteUnreachable when no answer comes: the host can't be reached, or is private
     */
    public function get(string $url, int $maxBytes, string $accept): RemoteResponse
    {
        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => $accept,
                    'Accept-Language' => 'es, en;q=0.8',
                ],
                'timeout' => 8,
                'max_duration' => 15,
                'max_redirects' => 5,
                'buffer' => false,
            ]);

            // Asked for first, so an answer of an error is read like any other.
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $body = '';
            $truncated = false;

            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();

                if (\strlen($body) > $maxBytes) {
                    $body = substr($body, 0, $maxBytes);
                    $truncated = true;
                    $response->cancel();

                    break;
                }
            }

            $effective = $response->getInfo('url');
            $length = $headers['content-length'][0] ?? null;
            $type = $headers['content-type'][0] ?? null;

            return new RemoteResponse(
                $status,
                \is_string($effective) ? $effective : $url,
                \is_int($response->getInfo('redirect_count')) && 0 < $response->getInfo('redirect_count'),
                null === $type ? null : strtolower(trim(explode(';', $type)[0])),
                null !== $length && ctype_digit($length) ? (int) $length : null,
                $body,
                $truncated,
            );
        } catch (ExceptionInterface $exception) {
            throw RemoteUnreachable::reading($url, $exception);
        }
    }
}
