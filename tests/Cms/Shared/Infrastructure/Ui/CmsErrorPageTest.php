<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure\Ui;

use Cms\Shared\Infrastructure\Ui\CmsErrorPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * What the page of an error of the CMS tells, and where it leads. Routes are
 * generated as their names; the texts are their translation keys, with what
 * their placeholders are given.
 */
final class CmsErrorPageTest extends TestCase
{
    private const string EDITOR = 'http://cms.blogautista.localhost/posts/01a1b835-7cfd-7864-b83b-873347d10bab';

    /**
     * What isn't there leads back to the list of its module, or to the CMS
     * when the address belongs to none.
     *
     * @param array<string, mixed> $way
     */
    #[DataProvider('missing')]
    public function testWhatIsNotThereLeadsBackToItsList(string $path, array $way): void
    {
        $page = $this->page(404, $path, self::EDITOR);

        $this->assertTells($page, 'not_found');
        PageComponents::one($page, 'Shared:ButtonLink', $way);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function missing(): iterable
    {
        yield 'post' => ['/posts/01a1b835-7cfd-7864-b83b-873347d10bab', ['label' => 'cms.error.back_to(%list%=cms.nav.posts)', 'url' => '/cms_posts']];
        yield 'page' => ['/pages/01a1b835-7cfd-7864-b83b-873347d10bab', ['label' => 'cms.error.back_to(%list%=cms.nav.pages)', 'url' => '/cms_pages']];
        yield 'image' => ['/images/01a1b835-7cfd-7864-b83b-873347d10bab', ['label' => 'cms.error.back_to(%list%=cms.nav.images)', 'url' => '/cms_images']];
        yield 'section' => ['/sections/01a1b835-7cfd-7864-b83b-873347d10bab', ['label' => 'cms.error.back_to(%list%=cms.nav.sections)', 'url' => '/cms_terms?taxonomy=sections']];
        yield 'tag' => ['/tags/01a1b835-7cfd-7864-b83b-873347d10bab', ['label' => 'cms.error.back_to(%list%=cms.nav.tags)', 'url' => '/cms_terms?taxonomy=tags']];
        yield 'author' => ['/authors/01a1b835-7cfd-7864-b83b-873347d10bab', ['label' => 'cms.error.back_to(%list%=cms.nav.authors)', 'url' => '/cms_authors']];
        yield 'external resource' => ['/externals/01a1b835-7cfd-7864-b83b-873347d10bab', ['label' => 'cms.error.back_to(%list%=cms.nav.externals)', 'url' => '/cms_externals']];
        yield 'redirect' => ['/redirects/01a1b835-7cfd-7864-b83b-873347d10bab', ['label' => 'cms.error.back_to(%list%=cms.nav.redirects)', 'url' => '/cms_redirects']];
        yield 'no module' => ['/nowhere', ['label' => 'cms.error.home', 'url' => '/cms_home']];
        yield 'the root' => ['/', ['label' => 'cms.error.home', 'url' => '/cms_home']];
    }

    /**
     * A preview link refused has expired or was changed, and leads nowhere:
     * whoever opens it may not be signed in to the CMS. Nor does a preview
     * of something deleted.
     */
    public function testAPreviewLinkRefusedSaysItExpiredAndLeadsNowhere(): void
    {
        foreach (['/preview/posts/01a1b835-7cfd-7864-b83b-873347d10bab', '/preview/01a1b835-7cfd-7864-b83b-873347d10bab'] as $path) {
            $refused = $this->page(403, $path, null);

            $this->assertTells($refused, 'preview');
            self::assertSame([], PageComponents::find($refused, 'Shared:ButtonLink', []));
        }

        $deleted = $this->page(404, '/preview/posts/01a1b835-7cfd-7864-b83b-873347d10bab', null);
        $this->assertTells($deleted, 'not_found');
        self::assertSame([], PageComponents::find($deleted, 'Shared:ButtonLink', []));
    }

    /**
     * Anything else asked wrong, a forbidden request outside the previews
     * included, leads back to the page of the CMS it came from, or to the
     * CMS when it came from none.
     */
    public function testARequestAskedWrongLeadsBackWhereItCameFrom(): void
    {
        foreach ([400, 403, 405, 422] as $status) {
            $back = $this->page($status, '/posts/01a1b835-7cfd-7864-b83b-873347d10bab', self::EDITOR);

            $this->assertTells($back, 'invalid');
            PageComponents::one($back, 'Shared:ButtonLink', ['label' => 'cms.error.go_back', 'url' => self::EDITOR]);
        }

        PageComponents::one($this->page(400, '/posts', null), 'Shared:ButtonLink', ['label' => 'cms.error.home', 'url' => '/cms_home']);
    }

    private function assertTells(Page $page, string $error): void
    {
        self::assertSame(\sprintf('cms.error.%s.title · cms.login.brand', $error), $page->title);
        PageComponents::one($page, 'Shared:PageTitle', ['title' => 'cms.error.'.$error.'.title', 'subtitle' => 'cms.error.'.$error.'.message']);
    }

    private function page(int $status, string $path, ?string $referer): Page
    {
        $page = new CmsErrorPage(new RouteNameUrlGenerator(), new CatalogueTranslator())($status, $path, $referer);
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
