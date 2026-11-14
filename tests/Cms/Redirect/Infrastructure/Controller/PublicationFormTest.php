<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\PublicationOutlook;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Infrastructure\Controller\PublicationForm;
use Cms\Redirect\Infrastructure\Controller\RedirectTargetField;
use Cms\Redirect\Infrastructure\Controller\ReplacedRedirectNotice;
use Cms\Tests\Page\Domain\PageMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * What the form that confirms publishing tells and asks: the redirect that
 * goes at the address the page takes, and what the address it leaves does,
 * leading to the page itself unless the editor says otherwise. It posts the
 * fingerprint of what it showed. The texts are their translation keys, with
 * what their placeholders are given.
 */
final class PublicationFormTest extends TestCase
{
    use NamesResources;

    private const string FINGERPRINT = 'f1ng3rpr1nt';

    public function testItPostsWhereItIsToldWithItsTokenAndTheFingerprintOfWhatItShowed(): void
    {
        $form = PageComponents::one($this->form(self::outlook()), 'Shared:Form', ['action' => '/publicar', 'method' => 'post', 'csrfTokenId' => 'cms_pages_publish']);

        PageComponents::one($form, 'Shared:FormInput', ['name' => 'outlook', 'type' => 'hidden', 'value' => self::FINGERPRINT]);
        PageComponents::one($form, 'Shared:FormButton', ['label' => 'Publicar']);
    }

    public function testItTellsOfTheRedirectThatGoesAtTheAddressThePageTakes(): void
    {
        $page = $this->form(self::outlook(replaced: RedirectResponse::fromRedirect(RedirectMother::create(source: '/quien-soy.html'))));

        PageComponents::one($page, 'Shared:Notice', ['message' => 'cms.redirects.publish.replaced(%address%=/quien-soy.html, %target%=cms.redirects.target.page(%name%=Destino))']);
    }

    public function testItAsksNothingOfAnAddressWhenThePageLeavesNone(): void
    {
        $page = $this->form(self::outlook(replaced: RedirectResponse::fromRedirect(RedirectMother::create(source: '/quien-soy.html'))));

        self::assertSame([], PageComponents::find($page, 'Shared:FormChoice', []));
    }

    /**
     * Unless the editor says otherwise, the address it leaves leads to the
     * page itself.
     */
    public function testItAsksWhatTheAddressThePageLeavesDoesLeadingToThePage(): void
    {
        $page = $this->form(self::outlook(previous: '/sobre-mi.html'));

        $panel = PageComponents::one($page, 'Shared:Panel', [
            'title' => 'cms.redirects.publish.previous.title',
            'description' => 'cms.redirects.publish.previous.description(%next%=/quien-soy.html, %previous%=/sobre-mi.html)',
        ]);
        $choice = PageComponents::one($panel, 'Shared:FormChoice', ['name' => 'previous[action]', 'legend' => 'cms.redirects.publish.previous.legend(%previous%=/sobre-mi.html)', 'value' => 'itself']);
        self::assertSame(['itself', ...RedirectTargetField::OTHER_KINDS, RedirectTargetField::GONE, 'keep'], self::options($choice));
        self::assertSame(
            ['label' => 'cms.redirects.publish.previous.itself.label', 'hint' => 'cms.redirects.publish.previous.itself.hint'],
            array_intersect_key(self::option($choice, 'itself'), ['label' => true, 'hint' => true]),
        );
        self::assertSame('cms.redirects.publish.previous.keep_none.label', self::option($choice, 'keep')['label']);
    }

    /**
     * With a redirect there, keeping the address keeps it, leading where it does.
     */
    public function testItTellsWhereTheAddressThePageLeavesLeadsWhenKept(): void
    {
        $page = $this->form(self::outlook(previous: '/sobre-mi.html', previousRedirect: RedirectResponse::fromRedirect(RedirectMother::gone(source: '/sobre-mi.html'))));

        self::assertSame(
            ['label' => 'cms.redirects.publish.previous.keep.label', 'hint' => 'cms.redirects.publish.previous.keep.hint(%target%=cms.redirects.target.gone)'],
            array_intersect_key(self::option(PageComponents::one($page, 'Shared:FormChoice', ['name' => 'previous[action]']), 'keep'), ['label' => true, 'hint' => true]),
        );
    }

    public function testItChoosesAgainWhatWasRefused(): void
    {
        $page = $this->form(self::outlook(previous: '/sobre-mi.html'), posted: ['previous' => ['action' => 'keep']]);

        PageComponents::one($page, 'Shared:FormChoice', ['name' => 'previous[action]', 'value' => 'keep']);
    }

    private static function outlook(?RedirectResponse $replaced = null, ?string $previous = null, ?RedirectResponse $previousRedirect = null): PublicationOutlook
    {
        return new PublicationOutlook('/quien-soy.html', $replaced, $previous, $previousRedirect, true, self::FINGERPRINT);
    }

    /**
     * @return list<string>
     */
    private static function options(PageComponent $choice): array
    {
        $options = $choice->props['options'];
        self::assertIsArray($options);

        return array_map(static fn (mixed $option): string => \is_array($option) && \is_string($option['value'] ?? null) ? $option['value'] : '', array_values($options));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function option(PageComponent $choice, string $value): array
    {
        $options = $choice->props['options'];
        self::assertIsArray($options);

        foreach ($options as $option) {
            if (\is_array($option) && $value === ($option['value'] ?? null)) {
                return $option;
            }
        }

        self::fail(\sprintf('No option "%s".', $value));
    }

    /**
     * @param array<array-key, mixed> $posted
     */
    private function form(PublicationOutlook $outlook, array $posted = []): Page
    {
        $translator = new CatalogueTranslator();
        $redirectNames = $this->redirectNames($translator, [PageMother::published(id: RedirectMother::PAGE, title: 'Destino', slug: 'destino')]);

        $components = new PublicationForm(
            $translator,
            new RedirectTargetField($translator, new RouteNameUrlGenerator()),
            new ReplacedRedirectNotice($redirectNames, $translator),
            $redirectNames,
        )($outlook, '/publicar', 'cms_pages_publish', 'Publicar', $posted);

        $page = Page::create('Publicar');

        foreach ($components as $component) {
            $page = $page->withMain($component->name, $component->props, $component->children);
        }

        // Whatever a test asks, the form gives every component what it takes.
        ComponentContracts::assertHonoured($page);

        return $page;
    }
}
