<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Infrastructure\Controller;

use Cms\Image\Domain\Image;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Infrastructure\Controller\ImageState;
use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Domain\PageType;
use Cms\Page\Infrastructure\Controller\PageRole;
use Cms\Page\Infrastructure\Controller\PageState;
use Cms\Relation\Application\UserResponse;
use Cms\Relation\Infrastructure\Controller\ImageUserRows;
use Cms\Relation\Infrastructure\Controller\PageUserRows;
use Cms\Relation\Infrastructure\Controller\SettingsUserRows;
use Cms\Relation\Infrastructure\Controller\UserRows;
use Cms\Settings\Domain\Settings;
use Cms\Settings\Domain\SettingsRepository;
use Cms\Settings\Infrastructure\Controller\SettingsState;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Settings\Domain\SettingsIdMother;
use Cms\Tests\Settings\Domain\SettingsMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Uuid;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;

/**
 * The rows of what uses a resource: each like those of its own list,
 * leading to where it is edited. Routes are generated as their names; the
 * texts are their translation keys, with what their placeholders are given.
 */
final class UserRowsTest extends TestCase
{
    private const string PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb01';
    private const string OTHER_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb02';
    private const string GONE_PAGE = '01a19e72-b07f-7d78-889c-fa4a14a4fb03';
    private const string IMAGE = '01a1b801-ce58-723d-8234-b76d9105d001';

    /**
     * Pages, then images, then the settings, whatever order they come in;
     * a resource gone meanwhile is left out.
     */
    public function testItGivesTheRowsOfPagesThenImagesThenTheSettings(): void
    {
        $rows = $this->rows(
            [
                new UserResponse('settings', SettingsIdMother::DEFAULT_VALUE),
                new UserResponse('image', self::IMAGE),
                new UserResponse('page', self::GONE_PAGE),
                new UserResponse('page', self::OTHER_PAGE),
                new UserResponse('page', self::PAGE),
            ],
            [PageMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'), PageMother::create(id: self::OTHER_PAGE, title: 'Contacto', slug: 'contacto')],
            [ImageMother::create(id: self::IMAGE)],
            SettingsMother::create(),
        );

        self::assertSame(
            ['/cms_pages_edit?id='.self::OTHER_PAGE, '/cms_pages_edit?id='.self::PAGE, '/cms_images_edit?id='.self::IMAGE, '/cms_settings'],
            array_map(static fn (PageComponent $row): mixed => $row->props['url'], $rows),
        );
    }

    /**
     * A page is told as a page, with when it last changed, where it stands
     * and the role it fills.
     */
    public function testItTellsOfAPageAsTheListOfPagesDoes(): void
    {
        $rows = $this->rows([new UserResponse('page', self::PAGE)], [PageMother::create(id: self::PAGE, title: 'Escríbeme', slug: 'contacto', type: PageType::Contact)]);

        self::assertEquals([new PageComponent('Shared:ResourceRow', [
            'title' => 'Escríbeme',
            'url' => '/cms_pages_edit?id='.self::PAGE,
            'meta' => 'cms.relations.types.page · cms.pages.list.updated_at(%date%=7 nov 2026)',
            'badge' => 'cms.pages.types.contact',
            'status' => 'cms.pages.status.draft.label',
        ])], $rows);
    }

    /**
     * An image is told as an image, by its thumbnail, with when it was
     * uploaded and where it stands.
     */
    public function testItTellsOfAnImageAsTheLibraryDoes(): void
    {
        $rows = $this->rows([new UserResponse('image', self::IMAGE)], images: [ImageMother::published(id: self::IMAGE, alt: 'Un faro', createdAt: '2026-10-01 09:00:00')]);

        self::assertEquals([new PageComponent('Shared:ResourceRow', [
            'title' => 'Un faro',
            'url' => '/cms_images_edit?id='.self::IMAGE,
            'meta' => 'cms.relations.types.image · cms.images.list.uploaded_at(%date%=1 oct 2026)',
            'thumbnail' => '/cms_images_file?id='.self::IMAGE,
            'status' => 'cms.images.status.published.label',
        ])], $rows);
    }

    /**
     * The settings are told as such, by the name of the blog, and where
     * they stand.
     */
    public function testItTellsOfTheSettingsByTheNameOfTheBlog(): void
    {
        $rows = $this->rows([new UserResponse('settings', SettingsIdMother::DEFAULT_VALUE)], settings: SettingsMother::create(SettingsMother::version(name: 'Blog autista')));

        self::assertEquals([new PageComponent('Shared:ResourceRow', [
            'title' => 'cms.settings.title',
            'url' => '/cms_settings',
            'meta' => 'cms.relations.types.settings · Blog autista',
            'status' => 'cms.settings.status.draft.label',
        ])], $rows);
    }

    /**
     * Each user is looked for among the resources of its type only, though
     * ids of different types may be the same, and the settings only while
     * they use the resource and exist.
     */
    public function testItLooksForEachUserAmongTheResourcesOfItsType(): void
    {
        $page = PageMother::create(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi');
        $image = ImageMother::create(id: self::PAGE);

        self::assertSame(['Sobre mí'], array_map(
            static fn (PageComponent $row): mixed => $row->props['title'],
            $this->rows([new UserResponse('page', self::PAGE)], [$page], [$image], SettingsMother::create()),
        ));
        self::assertSame([], $this->rows([new UserResponse('settings', SettingsIdMother::DEFAULT_VALUE)]));
        self::assertSame([], $this->rows([]));
    }

    /**
     * @param list<UserResponse> $users
     * @param list<Page>         $pages
     * @param list<Image>        $images
     *
     * @return list<PageComponent>
     */
    private function rows(array $users, array $pages = [], array $images = [], ?Settings $settings = null): array
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');
        // Each repository finds only what it is asked for, as the CMS would.
        $pageRepository = self::createStub(PageRepository::class);
        $pageRepository->method('findByIds')->willReturnCallback(static fn (array $ids): array => array_values(array_filter(
            $pages,
            static fn (Page $page): bool => \in_array($page->id()->value(), self::values($ids), true),
        )));
        $imageRepository = self::createStub(ImageRepository::class);
        $imageRepository->method('findByIds')->willReturnCallback(static fn (array $ids): array => array_values(array_filter(
            $images,
            static fn (Image $image): bool => \in_array($image->id()->value(), self::values($ids), true),
        )));
        $settingsRepository = self::createStub(SettingsRepository::class);
        $settingsRepository->method('find')->willReturn($settings);

        return new UserRows(
            new PageUserRows($translator, $urlGenerator, $pageRepository, new PageState($translator), new PageRole($translator)),
            new ImageUserRows($translator, $urlGenerator, $imageRepository, new ImageState($translator)),
            new SettingsUserRows($translator, $urlGenerator, $settingsRepository, new SettingsState($translator)),
        )($users);
    }

    /**
     * The values of the ids a repository is asked for.
     *
     * @param array<mixed> $ids
     *
     * @return list<string>
     */
    private static function values(array $ids): array
    {
        return array_values(array_map(static fn (mixed $id): string => $id instanceof Uuid ? $id->value() : '', $ids));
    }
}
