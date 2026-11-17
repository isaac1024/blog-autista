<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Author\Domain\AuthorRepository;
use Cms\Image\Domain\ImageRepository;
use Cms\Page\Application\PageResponse;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Infrastructure\Controller\PagePublishForm;
use Cms\Post\Domain\PostRepository;
use Cms\Redirect\Application\PublicationOutlook;
use Cms\Shared\Infrastructure\Publication\PublicationForm;
use Cms\Shared\Infrastructure\Publication\RedirectTargetField;
use Cms\Shared\Infrastructure\Publication\ReplacedRedirectNotice;
use Cms\Shared\Infrastructure\Ui\RedirectNames;
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
 * Where publishing a page is confirmed: titled after the page, with a way
 * back to its editor, and the form that confirms it, which publishes it.
 * What the form asks is told by PublicationFormTest. Routes are generated as
 * their names; the texts are their translation keys.
 */
final class PagePublishFormTest extends TestCase
{
    private const string ID = PageIdMother::DEFAULT_VALUE;

    public function testItIsTitledAfterThePageWithAWayBackToItsEditor(): void
    {
        $page = $this->compose();

        self::assertSame('cms.pages.publish.page_title(%title%=Sobre mí) · CMS', $page->title);
        $title = PageComponents::one($page, 'Shared:PageTitle', ['title' => 'cms.pages.publish.page_title(%title%=Sobre mí)', 'subtitle' => 'cms.redirects.publish.subtitle']);
        PageComponents::one($title, 'Shared:ButtonLink', ['url' => '/cms_pages_edit?id='.self::ID, 'label' => 'cms.pages.unpublish.back']);
    }

    public function testItsFormPublishesThePage(): void
    {
        $form = PageComponents::one($this->compose(), 'Shared:Form', ['action' => '/cms_pages_publish?id='.self::ID, 'csrfTokenId' => 'cms_pages_publish']);

        PageComponents::one($form, 'Shared:FormButton', ['label' => 'cms.pages.publish.submit']);
    }

    /**
     * What was sent and refused is chosen again.
     */
    public function testItChoosesAgainWhatWasRefused(): void
    {
        PageComponents::one($this->compose(['previous' => ['action' => 'keep']]), 'Shared:FormChoice', ['name' => 'previous[action]', 'value' => 'keep']);
    }

    /**
     * @param array<array-key, mixed> $saved
     */
    private function compose(array $saved = []): Page
    {
        $translator = new CatalogueTranslator();
        $urlGenerator = new RouteNameUrlGenerator();
        $redirectNames = new RedirectNames(self::createStub(PageRepository::class), self::createStub(ImageRepository::class), self::createStub(PostRepository::class), self::createStub(TermRepository::class), self::createStub(AuthorRepository::class), $translator);

        $page = new PagePublishForm($urlGenerator, $translator, new PublicationForm(
            $translator,
            new RedirectTargetField($translator, $urlGenerator),
            new ReplacedRedirectNotice($redirectNames, $translator),
            $redirectNames,
        ))(
            PageResponse::fromPage(PageMother::published(id: self::ID, title: 'Sobre mí', slug: 'sobre-mi')),
            new PublicationOutlook('/quien-soy.html', null, '/sobre-mi.html', null, true, 'f1ng3rpr1nt'),
            $saved,
        );
        // Whatever a test asks, the page gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
