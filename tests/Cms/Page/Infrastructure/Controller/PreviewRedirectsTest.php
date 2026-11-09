<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Controller;

use Cms\Page\Application\PageFinder;
use Cms\Page\Domain\PageRepository;
use Cms\Page\Infrastructure\Controller\PreviewPage;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Tests\Page\Domain\PageMother;
use Shared\Domain\MutualExclusion;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The preview follows the redirects as the CMS saves them, as the site will
 * once it catches up, so what is previewed is what is published.
 */
final class PreviewRedirectsTest extends KernelTestCase
{
    private const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string SECOND_OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a2';
    private const string TARGET = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string PREVIEWED = '0196f3a0-0000-7000-8000-0000000000c1';
    private const string HOST = 'www.blogautista.localhost';

    public function testItLinksAPageOffTheSiteAsThePageItsLastAddressLeadsTo(): void
    {
        $this->previewedLinking(self::OFF_SITE);
        $this->redirectFrom('/retirada.html', new TargetInput('page', self::TARGET));

        self::assertSame(['/destino.html'], $this->links());
    }

    public function testItFollowsTheLastAddressOfEveryPageOffTheSiteItLinks(): void
    {
        $this->previewedLinking(self::OFF_SITE, self::SECOND_OFF_SITE);
        $this->redirectFrom('/retirada.html', new TargetInput('page', self::TARGET));
        $this->redirectFrom('/segunda.html', new TargetInput(null, null), '0196f3a0-0000-7000-8000-0000000000e2');

        self::assertSame(['/destino.html', null], $this->links());
    }

    public function testItLeavesUnlinkedAPageOffTheSiteWhoseLastAddressIsGone(): void
    {
        $this->previewedLinking(self::OFF_SITE);
        $this->redirectFrom('/retirada.html', new TargetInput(null, null));

        self::assertSame([null], $this->links());
    }

    public function testItLeavesUnlinkedAPageOffTheSiteWhoseLastAddressLeadsBackToThePagePreviewed(): void
    {
        $this->previewedLinking(self::OFF_SITE, published: true);
        $this->redirectFrom('/retirada.html', new TargetInput('page', self::PREVIEWED));

        self::assertSame([null], $this->links());
    }

    private function previewedLinking(string $linked, ?string $alsoLinked = null, bool $published = false): void
    {
        self::bootKernel();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::unpublished(id: self::OFF_SITE, title: 'Retirada', slug: 'retirada'));
        $pages->save(PageMother::unpublished(id: self::SECOND_OFF_SITE, title: 'Segunda', slug: 'segunda'));
        $pages->save(PageMother::published(id: self::TARGET, title: 'Destino', slug: 'destino'));
        $text = [['text' => 'Enlace', 'marks' => ['link'], 'page_id' => $linked]];

        if (null !== $alsoLinked) {
            $text = [...$text, ['text' => ' '], ['text' => 'Otro', 'marks' => ['link'], 'page_id' => $alsoLinked]];
        }

        $content = ['version' => 1, 'blocks' => [['type' => 'paragraph', 'text' => $text]]];
        $pages->save($published
            ? PageMother::published(id: self::PREVIEWED, title: 'Vista previa', slug: 'vista-previa', content: $content)
            : PageMother::create(id: self::PREVIEWED, title: 'Vista previa', slug: 'vista-previa', content: $content, metaDescription: PageMother::META_DESCRIPTION));
    }

    private function redirectFrom(string $path, TargetInput $target, string $id = '0196f3a0-0000-7000-8000-0000000000e1'): void
    {
        new RedirectCreator($id, 'https://'.self::HOST.$path, $target, self::HOST)(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        );
    }

    /**
     * Where each stretch of the content previewed links to; null for none.
     *
     * @return list<?string>
     */
    private function links(): array
    {
        $page = (self::getContainer()->get(PreviewPage::class))(new PageFinder(self::PREVIEWED)(self::getContainer()->get(PageRepository::class)));
        $links = [];
        $walk = static function (array $components) use (&$walk, &$links): void {
            foreach ($components as $component) {
                if ($component instanceof PageComponent) {
                    if ('Shared:ContentParagraph' === $component->name) {
                        foreach (\is_array($component->props['text'] ?? null) ? $component->props['text'] : [] as $stretch) {
                            $links[] = \is_array($stretch) && \is_string($stretch['href'] ?? null) ? $stretch['href'] : null;
                        }
                    }

                    $walk($component->children);
                }
            }
        };
        $walk($page->main);

        return $links;
    }
}
