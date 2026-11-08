<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class PageControllerTest extends WebTestCase
{
    private const string HOST = 'http://www.blogautista.localhost';

    public function testItServesAPublishedPageAtItsAddress(): void
    {
        $client = self::createClient();
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(slug: 'sobre-mi')));

        $client->request('GET', self::HOST.'/sobre-mi.html');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('web_page', ['slug' => 'sobre-mi']);
    }

    /**
     * An embedded image on the site is painted; one off it is left out, and
     * the page is served all the same.
     */
    public function testItServesAPageThatEmbedsImagesOnTheSiteAndOffIt(): void
    {
        $client = self::createClient();
        $published = '01a1b801-ce58-723d-8234-b76d9105d001';
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(id: $published, path: '2026/11/'.$published.'.webp')));
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(slug: 'sobre-mi', content: [
            'version' => 1,
            'blocks' => [
                ['type' => 'image', 'id' => $published],
                ['type' => 'image', 'id' => '01a1b801-ce58-723d-8234-b76d9105d002'],
            ],
        ])));

        $client->request('GET', self::HOST.'/sobre-mi.html');

        self::assertResponseIsSuccessful();
    }

    public function testItServesThePublishedHomePageAtTheRoot(): void
    {
        $client = self::createClient();
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Inicio', slug: null, type: PageType::Home)));

        $client->request('GET', self::HOST.'/');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('web_home');
    }

    public function testItRespondsNotFoundWhenNoPageIsPublishedAtTheAddress(): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.'/sobre-mi.html');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItRespondsNotFoundWhenTheHomePageIsNotPublished(): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.'/');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[DataProvider('addressesThatAreNoSlug')]
    public function testItRespondsNotFoundForAnAddressThatIsNoSlug(string $path): void
    {
        $client = self::createClient();

        $client->request('GET', self::HOST.$path);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addressesThatAreNoSlug(): iterable
    {
        yield 'capital letters' => ['/Sobre-mi.html'];
        yield 'double hyphen' => ['/sobre--mi.html'];
        yield 'empty slug' => ['/.html'];
        yield 'without the extension' => ['/sobre-mi'];
    }

    public function testItOnlyServesTheSiteOnItsHost(): void
    {
        $client = self::createClient();
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(slug: 'sobre-mi')));

        $client->request('GET', 'http://cms.blogautista.localhost/sobre-mi.html');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
