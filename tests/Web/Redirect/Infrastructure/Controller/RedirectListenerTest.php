<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Redirect\Application\RedirectSaver;
use Web\Redirect\Domain\CmsRedirectSaved;
use Web\Redirect\Domain\RedirectRepository;
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

    /**
     * Two redirects at one address while the site catches up with the CMS,
     * which deleted one and created the other: the one changed last answers.
     */
    public function testTheRedirectChangedLastAnswersAnAddressTwoShare(): void
    {
        $client = $this->site();
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/vieja.html', null, null, '2026-11-09 10:00');
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e2', '/vieja.html', 'page', self::PAGE, '2026-11-09 11:00');

        $client->request('GET', self::HOST.'/vieja.html');

        self::assertResponseRedirects('/destino.html', Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sameAddresses(): iterable
    {
        yield 'with capitals' => ['/Guía-Antigua.html'];
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

    private function redirect(string $id, string $source, ?string $targetType, ?string $targetId, string $changedAt = '2026-11-09 10:00'): void
    {
        new RedirectSaver(new CmsRedirectSaved($id, $source, $targetType, $targetId, null, null, 'event-'.$id, new \DateTimeImmutable($changedAt), 1))(
            self::getContainer()->get(RedirectRepository::class),
        );
    }
}
