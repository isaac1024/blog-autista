<?php

declare(strict_types=1);

namespace Cms\Tests\Image\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Application\ImageResponse;
use Cms\Image\Domain\ImageRepository;
use Cms\Image\Infrastructure\Controller\ImageUnpublishForm;
use Cms\Page\Domain\PageRepository;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Infrastructure\Controller\RedirectNames;
use Cms\Redirect\Infrastructure\Controller\RedirectTargetField;
use Cms\Redirect\Infrastructure\Controller\RetirementForm;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Image\Domain\ImageIdMother;
use Cms\Tests\Image\Domain\ImageMother;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * Where an image is taken off the site: titled after it, with a way back to
 * its editor, and the form that takes it off. What the form asks is told by
 * RetirementFormTest. Routes are generated as their names; the texts are
 * their translation keys.
 */
final class ImageUnpublishFormTest extends TestCase
{
    private const string ID = ImageIdMother::DEFAULT_VALUE;

    public function testItIsTitledAfterItWithAWayBackToItsEditor(): void
    {
        $page = $this->compose();

        self::assertSame('cms.images.unpublish.page_title(%alt%=Cuadrado naranja sobre fondo azul) · CMS', $page->title);
        $title = PageComponents::one($page, 'Shared:PageTitle', ['title' => 'cms.images.unpublish.page_title(%alt%=Cuadrado naranja sobre fondo azul)', 'subtitle' => 'cms.redirects.retire.subtitle']);
        PageComponents::one($title, 'Shared:ButtonLink', ['url' => '/cms_images_edit?id='.self::ID, 'label' => 'cms.images.unpublish.back']);
    }

    public function testItsFormTakesItOff(): void
    {
        $form = PageComponents::one($this->compose(), 'Shared:Form', ['action' => '/cms_images_unpublish?id='.self::ID, 'csrfTokenId' => 'cms_images_unpublish']);

        PageComponents::one($form, 'Shared:FormButton', ['label' => 'cms.images.unpublish.submit']);
    }

    /**
     * What was sent and refused is chosen again.
     */
    public function testItChoosesAgainWhatWasRefused(): void
    {
        PageComponents::one($this->compose(['address' => ['action' => 'gone']]), 'Shared:FormChoice', ['name' => 'address[action]', 'value' => 'gone']);
    }

    /**
     * @param array<array-key, mixed> $saved
     */
    private function compose(array $saved = []): Page
    {
        $translator = new CatalogueTranslator();
        $urlGenerator = new RouteNameUrlGenerator();
        $siteResources = self::createStub(SiteResources::class);
        $siteResources->method('addressOf')->willReturnCallback(static fn (Resource $resource): ?Source => 'image' === $resource->type()->value && self::ID === $resource->id() ? Source::fromStored('/media/2026/11/'.self::ID) : null);

        $page = new ImageUnpublishForm($urlGenerator, $translator, new RetirementForm(
            $translator,
            new RedirectTargetField($translator, $urlGenerator),
            self::createStub(RedirectRepository::class),
            $siteResources,
            new RedirectNames(self::createStub(PageRepository::class), self::createStub(ImageRepository::class), self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $translator),
        ))(ImageResponse::fromImage(ImageMother::published(id: self::ID)), $saved);
        // Whatever a test asks, the page gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
