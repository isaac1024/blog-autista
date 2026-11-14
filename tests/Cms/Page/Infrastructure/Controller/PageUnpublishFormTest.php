<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Infrastructure\Controller\PageUnpublishForm;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Infrastructure\Controller\RedirectNames;
use Cms\Redirect\Infrastructure\Controller\RedirectTargetField;
use Cms\Redirect\Infrastructure\Controller\RetirementForm;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Page\Domain\PageIdMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * Where a page is taken off the site: titled after it, with a way back to
 * its editor, and the form that takes it off. What the form asks is told by
 * RetirementFormTest. Routes are generated as their names; the texts are
 * their translation keys.
 */
final class PageUnpublishFormTest extends TestCase
{
    private const string ID = PageIdMother::DEFAULT_VALUE;

    public function testItIsTitledAfterItWithAWayBackToItsEditor(): void
    {
        $page = $this->compose();

        self::assertSame('cms.pages.unpublish.page_title(%title%=Sobre mí) · CMS', $page->title);
        $title = PageComponents::one($page, 'Shared:PageTitle', ['title' => 'cms.pages.unpublish.page_title(%title%=Sobre mí)', 'subtitle' => 'cms.redirects.retire.subtitle']);
        PageComponents::one($title, 'Shared:ButtonLink', ['url' => '/cms_pages_edit?id='.self::ID, 'label' => 'cms.pages.unpublish.back']);
    }

    public function testItsFormTakesItOff(): void
    {
        $form = PageComponents::one($this->compose(), 'Shared:Form', ['action' => '/cms_pages_unpublish?id='.self::ID, 'csrfTokenId' => 'cms_pages_unpublish']);

        PageComponents::one($form, 'Shared:FormButton', ['label' => 'cms.pages.unpublish.submit']);
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
        $siteResources->method('addressOf')->willReturnCallback(static fn (Resource $resource): ?Source => 'page' === $resource->type()->value && self::ID === $resource->id() ? Source::fromStored('/sobre-mi.html') : null);

        $page = new PageUnpublishForm($urlGenerator, $translator, new RetirementForm(
            $translator,
            new RedirectTargetField($translator, $urlGenerator),
            self::createStub(RedirectRepository::class),
            $siteResources,
            new RedirectNames(self::createStub(PageRepository::class), self::createStub(ImageRepository::class), self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $translator),
        ))(PageResponse::fromPage(PageMother::published(id: self::ID, title: 'Sobre mí', slug: 'sobre-mi')), $saved);
        // Whatever a test asks, the page gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
