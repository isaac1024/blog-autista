<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Settings\Infrastructure\Controller\SiteErrorListener;
use Web\Settings\Infrastructure\Controller\SiteErrorResponse;

/**
 * Which errors the site answers with a page of its own: every one, only on
 * its own host, and a failure only when not debugging; an image is answered
 * without a page. What the page says is told by SiteErrorPageTest, and how
 * the kernel answers with it by SiteErrorPagesTest.
 */
final class SiteErrorListenerTest extends KernelTestCase
{
    private const string SITE = 'http://www.blogautista.localhost';

    /**
     * An address that answers nothing, no longer does, or not as asked, gets
     * a page of the site; one gone may be kept a while by browsers and
     * proxies, and a failure by none of them.
     */
    #[DataProvider('answered')]
    public function testItAnswersEveryErrorOfTheSite(\Throwable $exception, ?string $maxAge, bool $stored): void
    {
        $event = $this->dispatch(self::SITE.'/vieja.html', $exception, debug: false);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertNotSame('', $response->getContent());
        self::assertSame(null !== $maxAge, $response->headers->hasCacheControlDirective('public'));
        self::assertSame($maxAge, $response->headers->getCacheControlDirective('max-age'));
        self::assertSame(!$stored, $response->headers->hasCacheControlDirective('no-store'));
    }

    /**
     * @return iterable<string, array{\Throwable, ?string, bool}>
     */
    public static function answered(): iterable
    {
        yield 'never answered' => [new NotFoundHttpException(), null, true];
        yield 'no longer answered' => [new GoneHttpException(), '3600', true];
        yield 'forbidden' => [new AccessDeniedHttpException(), null, true];
        yield 'asked the wrong way' => [new MethodNotAllowedHttpException(['GET']), null, true];
        yield 'a failure of the site' => [new \RuntimeException(), null, false];
        yield 'the site unavailable' => [new ServiceUnavailableHttpException(), null, false];
    }

    /**
     * An image is asked for by a page, not read, so it gets no page.
     */
    #[DataProvider('images')]
    public function testItAnswersAnImageWithoutAPage(\Throwable $exception): void
    {
        $event = $this->dispatch(self::SITE.'/media/2026/11/01a1b801-ce58-723d-8234-b76d9105f001-1200w.webp', $exception, debug: false);

        self::assertSame('', $event->getResponse()?->getContent());
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function images(): iterable
    {
        yield 'never answered' => [new NotFoundHttpException()];
        yield 'failed' => [new \RuntimeException()];
    }

    /**
     * The errors of the CMS, and failures while debugging, are left to
     * whoever answers them.
     */
    #[DataProvider('leftAlone')]
    public function testItLeavesOtherErrorsAlone(string $url, \Throwable $exception, bool $debug): void
    {
        $event = $this->dispatch($url, $exception, $debug);

        self::assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{string, \Throwable, bool}>
     */
    public static function leftAlone(): iterable
    {
        yield 'an address of the CMS that answers nothing' => ['http://cms.blogautista.localhost/vieja', new NotFoundHttpException(), false];
        yield 'an address of the CMS no longer answered' => ['http://cms.blogautista.localhost/vieja', new GoneHttpException(), false];
        yield 'a failure of the CMS' => ['http://cms.blogautista.localhost/vieja', new \RuntimeException(), false];
        yield 'a failure of the site while debugging' => [self::SITE.'/vieja.html', new \RuntimeException(), true];
    }

    /**
     * A page that can't be painted, its settings unreadable, is left to the
     * error template, which reads nothing of the database.
     */
    public function testItLeavesAnErrorAloneWhenItsPageFailsToo(): void
    {
        self::getContainer()->set(SettingsRepository::class, new class implements SettingsRepository {
            #[\Override]
            public function save(Settings $settings): void {}

            #[\Override]
            public function find(): ?Settings
            {
                throw new \RuntimeException('The database is down.');
            }
        });

        $event = $this->dispatch(self::SITE.'/vieja.html', new \RuntimeException(), debug: false);

        self::assertNull($event->getResponse());
    }

    /**
     * Hands the error of a request to the listener, debugging or not: the
     * kernel of the tests always debugs, and one that doesn't would never
     * see a change to the code once its container is built.
     */
    private function dispatch(string $url, \Throwable $exception, bool $debug): ExceptionEvent
    {
        $request = Request::create($url);
        $event = new ExceptionEvent(self::$kernel ?? self::bootKernel(), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        // The request being answered, as the kernel keeps it for the templates.
        self::getContainer()->get(RequestStack::class)->push($request);
        $listener = new SiteErrorListener(self::getContainer()->get(SiteErrorResponse::class), new NullLogger(), 'www.blogautista.localhost', $debug);

        $listener($event);

        return $event;
    }
}
