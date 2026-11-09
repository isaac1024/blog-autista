<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;

/**
 * The site answers an address it has nothing of its own at by its redirect:
 * a permanent redirect to where the target is now, or gone.
 */
final class RedirectListenerTest extends WebTestCase
{
    private const string HOST = 'http://www.blogautista.localhost';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string HOME = '0196f3a0-0000-7000-8000-0000000000b2';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';

    public function testItRedirectsForGoodToThePageItLeadsTo(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/vieja.html', 'page', self::PAGE);

        $client->request('GET', self::HOST.'/vieja.html');

        self::assertResponseRedirects('/destino.html', Response::HTTP_MOVED_PERMANENTLY);
        // Kept for an hour only, so changing it reaches those who followed it.
        self::assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');
    }

    public function testItKeepsTheQueryOfTheAddressAskedFor(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/vieja.html', 'page', self::PAGE);

        $client->request('GET', self::HOST.'/vieja.html?utm_source=boletin');

        self::assertResponseRedirects('/destino.html?utm_source=boletin', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testItRedirectsToTheHomePageAtTheRoot(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/vieja.html', 'page', self::HOME);

        $client->request('GET', self::HOST.'/vieja.html');

        self::assertResponseRedirects('/', Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function widths(): iterable
    {
        yield 'the smallest' => [400];
        yield 'the middle one' => [800];
        yield 'the largest' => [1200];
    }

    #[DataProvider('widths')]
    public function testItRedirectsEveryCopyOfAnImageToTheSameWidthOfTheTarget(int $width): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/media/2026/11/0196f3a0-0000-7000-8000-0000000000c9', 'image', self::IMAGE);

        $client->request('GET', \sprintf('%s/media/2026/11/0196f3a0-0000-7000-8000-0000000000c9-%dw.webp', self::HOST, $width));

        self::assertResponseRedirects(\sprintf('/media/2026/11/%s-%dw.webp', self::IMAGE, $width), Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testItSaysAnAddressIsGoneWhenItsRedirectLeadsNowhere(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/retirada.html', null, null);

        $client->request('GET', self::HOST.'/retirada.html');

        self::assertResponseStatusCodeSame(Response::HTTP_GONE);
        self::assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');
    }

    /**
     * A target the site doesn't have yet, as it catches up with the CMS, is
     * no address to lead to.
     */
    public function testItFindsNothingWhileTheTargetIsNotOnTheSite(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/vieja.html', 'page', '0196f3a0-0000-7000-8000-0000000000ff');

        $client->request('GET', self::HOST.'/vieja.html');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItNeverHidesAPageOnTheSite(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/destino.html', null, null);

        $client->request('GET', self::HOST.'/destino.html');

        self::assertResponseIsSuccessful();
    }

    public function testEachAddressIsAnsweredByItsOwnRedirect(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/a-destino.html', 'page', self::PAGE);
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e2', '/a-portada.html', 'page', self::HOME);

        $client->request('GET', self::HOST.'/a-destino.html');

        self::assertResponseRedirects('/destino.html', Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * An address longer than any a redirect is kept at has none.
     */
    public function testItFindsNothingAtAnAddressLongerThanAnyRedirectHas(): void
    {
        $client = $this->site();

        $client->request('GET', self::HOST.'/'.str_repeat('a', 300).'.html');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{?string, int}>
     */
    public static function imagesWithNothing(): iterable
    {
        yield 'never there' => ['none', Response::HTTP_NOT_FOUND];
        yield 'taken off for good' => [null, Response::HTTP_GONE];
    }

    /**
     * An image is asked for by a page, to be shown, so it gets no page of
     * its own when the site has nothing there.
     */
    #[DataProvider('imagesWithNothing')]
    public function testItAnswersAnImageItHasNothingAtWithNoPage(?string $target, int $status): void
    {
        $client = $this->site();

        if (null === $target) {
            $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/media/2026/11/0196f3a0-0000-7000-8000-0000000000c9', null, null);
        }

        $client->request('GET', self::HOST.'/media/2026/11/0196f3a0-0000-7000-8000-0000000000c9-800w.webp');

        self::assertResponseStatusCodeSame($status);
        self::assertSame('', $client->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sameAddresses(): iterable
    {
        yield 'with capitals' => ['/Guía-Antigua.html'];
        yield 'all in capitals, accents too' => ['/GUÍA-ANTIGUA.html'];
        yield 'encoded in capitals' => ['/gu%C3%ADa-antigua.html'];
        yield 'through a dot segment' => ['/blog/../guía-antigua.html'];
    }

    /**
     * An address is written one way only, so it matches however the browser
     * asks for it.
     */
    #[DataProvider('sameAddresses')]
    public function testItAnswersAnAddressHoweverItIsAskedFor(string $path): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/gu%c3%ada-antigua.html', 'page', self::PAGE);

        $client->request('GET', self::HOST.$path);

        self::assertResponseRedirects('/destino.html', Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * An address no route answers, so the site finds nothing there whatever
     * the method.
     */
    public function testItOnlyRedirectsWhatIsReadNotWhatIsSent(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/archivo/viejo', 'page', self::PAGE);

        $client->request('POST', self::HOST.'/archivo/viejo');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * A client whose kernel holds the site: a page, the home page and an
     * image, as the CMS published them.
     */
    private function site(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::PAGE, title: 'Destino', slug: 'destino')));
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::HOME, title: 'Portada', slug: null, type: PageType::Home)));
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: self::IMAGE, path: '2026/11/'.self::IMAGE.'.webp')));

        return $client;
    }

    /**
     * A redirect as the CMS keeps it, which the site reads as it is.
     */
    private function redirect(string $id, string $source, ?string $targetType, ?string $targetId): void
    {
        self::getContainer()->get(Connection::class)->insert('cms_redirect', [
            'id' => $id, 'source' => $source, 'target_type' => $targetType, 'target_id' => $targetId,
            'origin_type' => null, 'origin_id' => null, 'created_at' => '2026-11-09 10:00', 'updated_at' => '2026-11-09 10:00', 'version' => 1,
        ]);
    }
}
