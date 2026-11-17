<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Controller;

use Doctrine\DBAL\Connection;
use Shared\Infrastructure\Ui\Page\Page as PaintedPage;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Image\Domain\Image;
use Web\Image\Domain\ImageRepository;
use Web\Page\Application\PageResponse;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Page\Infrastructure\Controller\PublishedPage;
use Web\Settings\Domain\Settings;
use Web\Settings\Domain\SettingsRepository;
use Web\Tests\Image\Domain\CmsImagePublishedMother;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Settings\Domain\CmsSettingsPublishedMother;

/**
 * A page of the site shows what it refers to off the site as what the last
 * address of each one now redirects to: the target, if on the site, and
 * nothing when that address is gone, has no redirect, or leads back to the
 * page itself. One redirect is followed at most.
 */
final class RedirectedReferencesTest extends KernelTestCase
{
    /** Off the site, redirected to B. */
    private const string A = '0196f3a0-0000-7000-8000-00000000000a';
    private const string B = '0196f3a0-0000-7000-8000-00000000000b';
    /** The page painted. */
    private const string C = '0196f3a0-0000-7000-8000-00000000000c';
    /** Off the site, redirected to C itself. */
    private const string D = '0196f3a0-0000-7000-8000-00000000000d';
    /** Off the site, its address gone. */
    private const string E = '0196f3a0-0000-7000-8000-00000000000e';
    /** An image off the site, redirected to Y. */
    private const string X = '0196f3a0-0000-7000-8000-0000000000f1';
    private const string Y = '0196f3a0-0000-7000-8000-0000000000f2';
    private const string HOME = '0196f3a0-0000-7000-8000-0000000000f9';
    /** A page all of whose links are on the site. */
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000f8';

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $link = static fn (string $text, string $id): array => ['text' => $text, 'marks' => ['link'], 'page_id' => $id];
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::B, title: 'Página B', slug: 'pagina-b')));
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::HOME, title: 'Portada', slug: null, type: PageType::Home)));
        $pages->save(Page::publish(CmsPagePublishedMother::create(id: self::C, title: 'Página C', slug: 'pagina-c', content: [
            'version' => 1,
            'blocks' => [
                ['type' => 'paragraph', 'text' => [$link('A', self::A), ['text' => ' '], $link('D', self::D), ['text' => ' '], $link('E', self::E)]],
                ['type' => 'image', 'id' => self::X],
                ['type' => 'widget', 'kind' => 'related_page', 'page_id' => self::A],
            ],
        ])));
        // The caption of Y links to B, so a page painted with Y links to B.
        self::getContainer()->get(ImageRepository::class)->save(Image::publish(CmsImagePublishedMother::create(
            id: self::Y,
            alt: 'Imagen Y',
            caption: ['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => [$link('Pie', self::B)]]]],
            path: '2026/11/'.self::Y.'.webp',
        )));
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e1', '/pagina-a.html', 'page', self::B, 'page', self::A);
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e2', '/pagina-d.html', 'page', self::C, 'page', self::D);
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e3', '/pagina-e.html', null, null, 'page', self::E);
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e4', '/media/2026/11/'.self::X, 'image', self::Y, 'image', self::X);
    }

    public function testItLinksAPageOffTheSiteAsThePageItsAddressLeadsTo(): void
    {
        self::assertSame('/pagina-b.html', $this->links($this->paint())['A']);
    }

    /**
     * Two redirects know A as their origin while the site catches up with
     * the CMS: the one changed last counts.
     */
    public function testItFollowsTheRedirectChangedLastOfTwoThatKnowThePage(): void
    {
        $this->redirect('0196f3a0-0000-7000-8000-0000000000e5', '/otra-a.html', 'page', self::C, 'page', self::A, '2026-11-09 08:00');

        self::assertSame('/pagina-b.html', $this->links($this->paint())['A']);
    }

    public function testItLinksAsTheyArePagesOnTheSite(): void
    {
        self::getContainer()->get(PageRepository::class)->save(Page::publish(CmsPagePublishedMother::create(id: self::OTHER, title: 'Otra', slug: 'otra', content: [
            'version' => 1,
            'blocks' => [['type' => 'paragraph', 'text' => [
                ['text' => 'B', 'marks' => ['link'], 'page_id' => self::B],
                ['text' => ' '],
                ['text' => 'Home', 'marks' => ['link'], 'page_id' => self::HOME],
            ]]],
        ])));

        self::assertSame(['B' => '/pagina-b.html', '' => null, 'Home' => '/'], $this->links($this->paint('otra')));
    }

    public function testItLeavesUnlinkedAPageWhoseAddressIsGone(): void
    {
        self::assertNull($this->links($this->paint())['E']);
    }

    public function testItLeavesUnlinkedAPageWhoseAddressLeadsBackToThePagePainted(): void
    {
        self::assertNull($this->links($this->paint())['D']);
    }

    public function testItEmbedsAnImageOffTheSiteAsTheImageItsAddressLeadsTo(): void
    {
        $image = $this->components($this->paint(), 'Shared:ContentImage')[0] ?? self::fail('No image painted.');

        self::assertSame('/media/2026/11/'.self::Y.'-1200w.webp', $image->props['src']);
        self::assertSame('Imagen Y', $image->props['alt']);
        // Its caption links where the caption of Y does.
        $caption = $image->props['caption'];
        self::assertIsArray($caption);
        self::assertIsArray($caption[0] ?? null);
        self::assertSame('/pagina-b.html', $caption[0]['href'] ?? null);
    }

    public function testItShowsTheWidgetOfAPageOffTheSiteAsThePageItsAddressLeadsTo(): void
    {
        $widget = $this->components($this->paint(), 'Shared:ContentRelated')[0] ?? self::fail('No widget painted.');

        self::assertSame(['title' => 'Página B', 'url' => '/pagina-b.html'], ['title' => $widget->props['title'], 'url' => $widget->props['url']]);
    }

    public function testItLinksTheMenusAndSharesTheDefaultImageAsWhereTheirAddressesLead(): void
    {
        Settings::publish(CmsSettingsPublishedMother::create(
            defaultImageId: self::X,
            mainMenu: [['page_id' => self::A, 'label' => null], ['page_id' => self::E, 'label' => 'Retirada']],
            footerMenu: [['page_id' => self::A, 'label' => 'Etiqueta propia']],
        ))->save(self::getContainer()->get(SettingsRepository::class));

        $page = $this->paint();

        $header = $this->components($page, 'Shared:PageHeader')[0] ?? self::fail('No header painted.');
        $footer = $this->components($page, 'Shared:PageFooter')[0] ?? self::fail('No footer painted.');
        self::assertSame([['label' => 'Página B', 'url' => '/pagina-b.html']], $header->props['links']);
        self::assertSame([['label' => 'Etiqueta propia', 'url' => '/pagina-b.html']], $footer->props['links']);
        self::assertStringEndsWith('/media/2026/11/'.self::Y.'-1200w.webp', $page->image['src'] ?? '');
    }

    private function paint(string $slug = 'pagina-c'): PaintedPage
    {
        $page = self::getContainer()->get(PageRepository::class)->findBySlug($slug) ?? self::fail('No page to paint.');

        return (self::getContainer()->get(PublishedPage::class))(PageResponse::fromPage($page));
    }

    /**
     * Where each run of the content links to, by its text; null for none.
     *
     * @return array<string, ?string>
     */
    private function links(PaintedPage $page): array
    {
        $links = [];

        foreach ($this->components($page, 'Shared:ContentParagraph') as $paragraph) {
            $stretches = $paragraph->props['text'];
            self::assertIsArray($stretches);

            foreach ($stretches as $stretch) {
                self::assertIsArray($stretch);
                self::assertIsArray($stretch['runs']);
                $href = $stretch['href'];
                self::assertTrue(null === $href || \is_string($href));

                foreach ($stretch['runs'] as $run) {
                    self::assertIsArray($run);
                    self::assertIsString($run['text']);
                    $links[trim($run['text'])] = $href;
                }
            }
        }

        return $links;
    }

    /**
     * Every component of the name, anywhere in the page.
     *
     * @return list<PageComponent>
     */
    private function components(PaintedPage $page, string $name): array
    {
        $found = [];
        $walk = static function (array $components) use (&$walk, &$found, $name): void {
            foreach ($components as $component) {
                if ($component instanceof PageComponent) {
                    if ($name === $component->name) {
                        $found[] = $component;
                    }

                    $walk($component->children);
                }
            }
        };
        $walk([...$page->header, ...$page->main, ...$page->footer]);

        return $found;
    }

    /**
     * A redirect as the CMS keeps it, which the site reads as it is.
     */
    private function redirect(string $id, string $source, ?string $targetType, ?string $targetId, string $originType, string $originId, string $changedAt = '2026-11-09 10:00'): void
    {
        self::getContainer()->get(Connection::class)->insert('shared_redirect', [
            'id' => $id, 'source' => $source, 'target_type' => $targetType, 'target_id' => $targetId,
            'origin_type' => $originType, 'origin_id' => $originId, 'created_at' => $changedAt, 'updated_at' => $changedAt, 'version' => 1,
        ]);
    }
}
