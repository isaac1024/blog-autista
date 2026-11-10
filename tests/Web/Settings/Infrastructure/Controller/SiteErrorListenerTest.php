<?php

declare(strict_types=1);

namespace Web\Tests\Settings\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Web\Settings\Infrastructure\Controller\SiteErrorListener;

/**
 * Which errors the site answers with a page of its own: an address it
 * doesn't answer, or no longer does, and only on its own host; an image is
 * answered without a page. What the page says is told by SiteErrorPageTest.
 */
final class SiteErrorListenerTest extends KernelTestCase
{
    private const string SITE = 'http://www.blogautista.localhost';

    /**
     * An address that answers nothing, or no longer does, gets a page of
     * the site; one gone may be kept a while by browsers and proxies.
     */
    #[DataProvider('answered')]
    public function testItAnswersAnAddressOfTheSiteThatAnswersNothing(\Throwable $exception, bool $cached): void
    {
        $event = $this->dispatch(self::SITE.'/vieja.html', $exception);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertNotSame('', $response->getContent());
        self::assertSame($cached, $response->headers->hasCacheControlDirective('public'));
        self::assertSame($cached ? '3600' : null, $response->headers->getCacheControlDirective('max-age'));
    }

    /**
     * @return iterable<string, array{\Throwable, bool}>
     */
    public static function answered(): iterable
    {
        yield 'never answered' => [new NotFoundHttpException(), false];
        yield 'no longer answered' => [new GoneHttpException(), true];
    }

    /**
     * An image is asked for by a page, not read, so it gets no page.
     */
    public function testItAnswersAnImageWithoutAPage(): void
    {
        $event = $this->dispatch(self::SITE.'/media/2026/11/01a1b801-ce58-723d-8234-b76d9105f001-1200w.webp', new NotFoundHttpException());

        self::assertSame('', $event->getResponse()?->getContent());
    }

    /**
     * Other errors, and those of the CMS, are left to whoever answers them.
     */
    #[DataProvider('leftAlone')]
    public function testItLeavesOtherErrorsAlone(string $url, \Throwable $exception): void
    {
        $event = $this->dispatch($url, $exception);

        self::assertNull($event->getResponse());
    }

    /**
     * @return iterable<string, array{string, \Throwable}>
     */
    public static function leftAlone(): iterable
    {
        yield 'another error of the site' => [self::SITE.'/vieja.html', new AccessDeniedHttpException()];
        yield 'an address of the CMS that answers nothing' => ['http://cms.blogautista.localhost/vieja', new NotFoundHttpException()];
        yield 'an address of the CMS no longer answered' => ['http://cms.blogautista.localhost/vieja', new GoneHttpException()];
        yield 'a failure of the site' => [self::SITE.'/vieja.html', new \RuntimeException()];
    }

    private function dispatch(string $url, \Throwable $exception): ExceptionEvent
    {
        $request = Request::create($url);
        $event = new ExceptionEvent(self::bootKernel(), $request, HttpKernelInterface::MAIN_REQUEST, $exception);
        // The request being answered, as the kernel keeps it for the templates.
        self::getContainer()->get(RequestStack::class)->push($request);

        self::getContainer()->get(SiteErrorListener::class)($event);

        return $event;
    }
}
