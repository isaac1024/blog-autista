<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageResponse;
use Cms\Image\Application\ImagesResponse;
use Cms\Image\Domain\Alt;
use Cms\Image\Domain\Image;
use Cms\Image\Infrastructure\Controller\ImageList;
use Cms\Image\Infrastructure\Controller\ImageState;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\ListPagination;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the image library makes: how an image is uploaded, what it
 * lists, what it says when there is nothing to list, and where each image
 * and each search leads. Routes are generated as their names; the texts are
 * their translation keys, with what their placeholders are given.
 */
final class ImageListTest extends TestCase
{
    private const string ID = '01a1b801-ce58-723d-8234-b76d9105f001';

    /**
     * An image is uploaded cropped and encoded in the browser, at the size
     * the site keeps, with its alternative text: the two the upload reads.
     */
    public function testItUploadsAnImageCroppedWithItsAlternativeText(): void
    {
        $list = $this->list([], '', 1, false);

        $form = PageComponents::one($list, 'Shared:Form', ['action' => '/cms_images_create', 'enctype' => 'multipart/form-data']);
        PageComponents::one($form, 'Shared:ImageCropper', ['name' => 'image', 'width' => Image::WIDTH, 'height' => Image::HEIGHT, 'quality' => 0.85]);
        $alt = PageComponents::one($form, 'Shared:FormTextarea', ['name' => 'alt', 'singleLine' => true, 'recommendedMax' => Alt::MAX_LENGTH]);
        self::assertSame(['required' => true, 'maxlength' => Alt::MAX_LENGTH], array_intersect_key(self::attributes($alt), ['required' => 0, 'maxlength' => 0]));
        self::assertCount(1, PageComponents::find($form, 'Shared:FormButton', []));
    }

    /**
     * The search box keeps what was searched, as the search took it.
     */
    public function testItKeepsTheSearchInItsBox(): void
    {
        $list = $this->list([ImageMother::create(id: self::ID)], '  faro ', 1, false);

        PageComponents::one($list, 'Shared:SearchForm', ['action' => '/cms_images', 'name' => 'alt', 'value' => 'faro']);
    }

    /**
     * With no image at all, the library invites to upload the first one;
     * with none found, it tells what was searched and leads back to every
     * image.
     */
    #[DataProvider('nothingToList')]
    public function testItTellsWhyThereIsNothingToList(string $search, string $title, string $message, PageComponent $action): void
    {
        $list = $this->list([], $search, 1, false);

        $empty = PageComponents::one($list, 'Shared:EmptyState', ['title' => $title, 'message' => $message]);
        PageComponents::one($empty, $action->name, $action->props);
        self::assertCount(1, $empty->children);
        self::assertSame([], PageComponents::find($list, 'Shared:ResourceList', []));
        self::assertSame([], PageComponents::find($list, 'Shared:Pagination', []));
    }

    /**
     * @return iterable<string, array{string, string, string, PageComponent}>
     */
    public static function nothingToList(): iterable
    {
        yield 'no image at all' => ['', 'cms.images.empty.title', 'cms.images.empty.message', new PageComponent('Shared:ModalTrigger', ['target' => 'upload-image'])];
        yield 'no image found' => [' faro ', 'cms.images.search.empty.title', 'cms.images.search.empty.message(%alt%=faro)', new PageComponent('Shared:ButtonLink', ['url' => '/cms_images'])];
    }

    public function testItOpensTheModalToUploadTheFirstImage(): void
    {
        $list = $this->list([], '', 1, false);

        PageComponents::one($list, 'Shared:Modal', ['id' => 'upload-image']);
    }

    /**
     * Each image opens its editor, by its thumbnail and its alternative
     * text, and tells when it was uploaded and where it stands.
     */
    public function testItListsEachImageLeadingToItsEditor(): void
    {
        $list = $this->list([ImageMother::published(id: self::ID, alt: 'Un faro al anochecer', createdAt: '2026-10-01 09:00:00')], '', 1, false);

        PageComponents::one($list, 'Shared:ResourceRow', [
            'title' => 'Un faro al anochecer',
            'url' => '/cms_images_edit?id='.self::ID,
            'meta' => 'cms.images.list.uploaded_at(%date%=1 oct 2026)',
            'thumbnail' => '/cms_images_file?id='.self::ID,
            'status' => 'cms.images.status.published.label',
        ]);
    }

    /**
     * The library goes on through the pages of its search, and the first
     * one goes without a number.
     */
    #[DataProvider('pagination')]
    public function testItLeadsThroughThePagesOfTheSearch(string $search, int $number, bool $hasMore, ?string $previous, ?string $next): void
    {
        $list = $this->list([ImageMother::create(id: self::ID)], $search, $number, $hasMore);

        PageComponents::one($list, 'Shared:Pagination', ['previousUrl' => $previous, 'nextUrl' => $next]);
    }

    /**
     * @return iterable<string, array{string, int, bool, ?string, ?string}>
     */
    public static function pagination(): iterable
    {
        yield 'the only page' => ['', 1, false, null, null];
        yield 'the first of many' => ['', 1, true, null, '/cms_images?page=2'];
        yield 'one in between' => ['', 2, true, '/cms_images', '/cms_images?page=3'];
        yield 'one of a search' => [' faro ', 2, true, '/cms_images?alt=faro', '/cms_images?alt=faro&page=3'];
    }

    /**
     * @return array<mixed>
     */
    private static function attributes(PageComponent $field): array
    {
        $attributes = $field->props['attributes'] ?? [];
        self::assertIsArray($attributes);

        return $attributes;
    }

    /**
     * @param list<Image> $images
     */
    private function list(array $images, string $search, int $number, bool $hasMore): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();
        $translator->setLocale('es');

        $list = new ImageList(
            $urlGenerator,
            $translator,
            new ImageState($translator),
            new ListPagination($translator, $urlGenerator),
        )(new ImagesResponse(array_map(ImageResponse::fromImage(...), $images), $hasMore), $search, $number);
        // Whatever a test asks, the library gives every component what it takes.
        ComponentContracts::assertHonoured($list);

        return $list;
    }
}
