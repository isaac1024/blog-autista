<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Http;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Http\FrameSources;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A page that frames resources of their providers lets those origins, and
 * only those, frame in its policy; any other page keeps its policy as it
 * was.
 */
final class FrameSourcesTest extends TestCase
{
    private const string POLICY = "default-src 'self'; script-src 'self'";

    /**
     * @param list<string> $origins what the page frames
     */
    #[DataProvider('policies')]
    public function testItLetsThePageFrameItsProvidersAlone(string $policy, array $origins, string $expected): void
    {
        $request = new Request();
        FrameSources::allow($request, $origins);

        self::assertSame($expected, $this->respond($request, $policy));
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function policies(): iterable
    {
        $youtube = 'https://www.youtube-nocookie.com';
        $spotify = 'https://open.spotify.com';

        yield 'a policy that frames nothing yet' => [self::POLICY, [$youtube, $spotify], self::POLICY.'; frame-src '.$youtube.' '.$spotify];
        yield 'a policy that frames already, written loosely' => [" default-src 'self' ;  frame-src 'self' ; ", [$youtube], "default-src 'self'; frame-src 'self' ".$youtube];
        yield 'origins that are no plain origin, left out' => [self::POLICY, ['http://inseguro.example', 'https://x.example/ruta', "https://a.example 'unsafe-inline'", $youtube], self::POLICY.'; frame-src '.$youtube];
        yield 'nothing but origins left out' => [self::POLICY, ['javascript:alert(1)'], self::POLICY];
        yield 'nothing framed' => [self::POLICY, [], self::POLICY];
    }

    public function testItAllowsEachOriginOnceHoweverOftenThePageFramesIt(): void
    {
        $request = new Request();
        FrameSources::allow($request, ['https://open.spotify.com', 'https://www.youtube-nocookie.com']);
        FrameSources::allow($request, ['https://www.youtube-nocookie.com', 'https://embed.bsky.app']);

        self::assertSame(self::POLICY.'; frame-src https://open.spotify.com https://www.youtube-nocookie.com https://embed.bsky.app', $this->respond($request, self::POLICY));
    }

    /**
     * What another part of the app left under its name that is no origin
     * never reaches the policy.
     */
    public function testItKeepsOutWhatIsNoOriginLeftBefore(): void
    {
        $request = new Request();
        $request->attributes->set('_frame_sources', [3, 'https://open.spotify.com']);
        FrameSources::allow($request, ['https://embed.bsky.app']);

        self::assertSame(self::POLICY.'; frame-src https://open.spotify.com https://embed.bsky.app', $this->respond($request, self::POLICY));
    }

    public function testItLeavesAResponseWithoutPolicyAsItIs(): void
    {
        $request = new Request();
        FrameSources::allow($request, ['https://open.spotify.com']);
        $response = new Response();

        new FrameSources()(new ResponseEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertFalse($response->headers->has('Content-Security-Policy'));
    }

    /**
     * A fragment the page includes answers within it, under the policy of
     * the page.
     */
    public function testItLeavesTheAnswerOfAFragmentAsItIs(): void
    {
        $request = new Request();
        FrameSources::allow($request, ['https://open.spotify.com']);

        self::assertSame(self::POLICY, $this->respond($request, self::POLICY, HttpKernelInterface::SUB_REQUEST));
    }

    private function respond(Request $request, string $policy, int $type = HttpKernelInterface::MAIN_REQUEST): ?string
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy', $policy);

        new FrameSources()(new ResponseEvent($this->kernel(), $request, $type, $response));

        return $response->headers->get('Content-Security-Policy');
    }

    private function kernel(): HttpKernelInterface
    {
        return self::createStub(HttpKernelInterface::class);
    }
}
