<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Controller;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\RichTextComponents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Page\Infrastructure\Controller\PublishedPage;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class PublishedPageTest extends TestCase
{
    private const string TARGET = '01a1b801-ce58-723d-8234-b76d9105d001';
    private const string HOME = '01a1b801-ce58-723d-8234-b76d9105d002';
    private const string MISSING = '01a1b801-ce58-723d-8234-b76d9105d003';

    public function testItResolvesCurrentAddressesOfPublishedLinkTargetsOnly(): void
    {
        $content = [
            'version' => 1,
            'blocks' => [[
                'type' => 'paragraph',
                'text' => [
                    ['text' => 'Destino', 'marks' => ['link'], 'page_id' => self::TARGET],
                    ['text' => 'Portada', 'marks' => ['link'], 'page_id' => self::HOME],
                    ['text' => 'No publicada', 'marks' => ['link'], 'page_id' => self::MISSING],
                ],
            ]],
        ];
        $page = Page::publish(CmsPagePublishedMother::create(content: $content));
        $target = Page::publish(CmsPagePublishedMother::create(id: self::TARGET, title: 'Destino', slug: 'destino'));
        $home = Page::publish(CmsPagePublishedMother::create(id: self::HOME, title: 'Portada', slug: null, type: PageType::Home));

        $urlGenerator = self::createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $name, array $parameters = []): string {
                if ('web_home' === $name) {
                    return '/';
                }

                $slug = $parameters['slug'] ?? null;

                return \is_string($slug) ? '/'.$slug.'.html' : '';
            },
        );
        $imageRepository = $this->createMock(ImageRepository::class);
        $imageRepository->expects(self::once())->method('findByIds')->with([])->willReturn([]);
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects(self::once())
            ->method('findByIds')
            ->with(self::equalTo([
                PageIdMother::create(self::TARGET),
                PageIdMother::create(self::HOME),
                PageIdMother::create(self::MISSING),
            ]))
            ->willReturn([$target, $home]);

        $result = new PublishedPage($urlGenerator, new RichTextComponents(), $imageRepository, $pageRepository)(PageResponse::fromPage($page));

        self::assertSame([
            ['href' => '/destino.html', 'runs' => [['text' => 'Destino', 'bold' => false]]],
            ['href' => '/', 'runs' => [['text' => 'Portada', 'bold' => false]]],
            ['href' => null, 'runs' => [['text' => 'No publicada', 'bold' => false]]],
        ], $result->main[1]->children[0]->props['text']);
    }
}
