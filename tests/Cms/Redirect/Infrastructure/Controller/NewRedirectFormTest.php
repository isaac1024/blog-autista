<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Infrastructure\Controller\NewRedirectForm;
use Cms\Redirect\Infrastructure\Controller\RedirectTargetField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\SitePath;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;

/**
 * The decisions the form that creates a redirect makes: which address it
 * takes, whether it may be changed, and where it may lead. Routes are
 * generated as their names; the texts are their translation keys, with
 * what their placeholders are given.
 */
final class NewRedirectFormTest extends TestCase
{
    private const string ADDRESS = RouteNameUrlGenerator::SITE.'/vieja.html';

    /**
     * The address is a URL of the site, as long as the site and its longest
     * path, written as an example shows; the creation reads it, with where it
     * leads.
     */
    public function testItCreatesARedirectFromAnAddressOfTheSite(): void
    {
        $form = PageComponents::one($this->form('', null), 'Shared:Form', ['action' => '/cms_redirects_create']);

        $source = PageComponents::one($form, 'Shared:FormInput', ['name' => 'source', 'type' => 'url', 'value' => '']);
        self::assertSame(
            ['required' => true, 'readonly' => false, 'maxlength' => \strlen(RouteNameUrlGenerator::SITE.'/') + SitePath::MAX_LENGTH],
            array_intersect_key((array) $source->props['attributes'], ['required' => 0, 'readonly' => 0, 'maxlength' => 0]),
        );
        PageComponents::one($form, 'Shared:FormHint', ['text' => 'cms.redirects.form.source.hint(%example%='.RouteNameUrlGenerator::SITE.'/una-pagina-antigua.html)']);
        self::assertCount(1, PageComponents::find($form, 'Shared:FormButton', []));
    }

    /**
     * A new redirect may lead to a page, which it does at first, to an
     * image, or tell its address is gone.
     */
    public function testItLetsTheRedirectLeadToAPageAnImageOrNowhere(): void
    {
        $choice = PageComponents::one($this->form('', null), 'Shared:FormChoice', ['name' => 'action', 'value' => 'page']);

        self::assertSame(['page', 'image', 'gone'], array_column((array) $choice->props['options'], 'value'));
        PageComponents::one($choice, 'Shared:ResourceField', ['name' => 'page_id', 'value' => '']);
        PageComponents::one($choice, 'Shared:ImageField', ['name' => 'image_id', 'value' => '']);
    }

    /**
     * An address written already comes back as it was; one given, as the
     * last of a resource off the site, can't be changed, says why, and stays
     * given if the creation is refused. Only a written address can be given.
     */
    #[DataProvider('addresses')]
    public function testItKeepsTheAddressItComesWith(string $source, ?bool $fixed, bool $given): void
    {
        $form = $this->form($source, $fixed);

        $input = PageComponents::one($form, 'Shared:FormInput', ['name' => 'source', 'value' => $source]);
        self::assertSame($given, ((array) $input->props['attributes'])['readonly']);
        self::assertCount($given ? 1 : 0, PageComponents::find($form, 'Shared:Notice', ['message' => 'cms.redirects.new.fixed']));
        self::assertCount($given ? 1 : 0, PageComponents::find($form, 'Shared:FormInput', ['name' => 'fixed', 'type' => 'hidden', 'value' => '1']));
    }

    /**
     * @return iterable<string, array{string, ?bool, bool}>
     */
    public static function addresses(): iterable
    {
        yield 'none' => ['', null, false];
        yield 'one written' => [self::ADDRESS, null, false];
        yield 'one written, asked not to be given' => [self::ADDRESS, false, false];
        yield 'one given' => [self::ADDRESS, true, true];
        yield 'none, asked to be given' => ['', true, false];
    }

    public function testItLeadsBackToEveryRedirect(): void
    {
        PageComponents::one($this->form('', null), 'Shared:ButtonLink', ['url' => '/cms_redirects']);
    }

    private function form(string $source, ?bool $fixed): Page
    {
        $urlGenerator = new RouteNameUrlGenerator();
        $translator = new CatalogueTranslator();

        $form = new NewRedirectForm($urlGenerator, $translator, new RedirectTargetField($translator, $urlGenerator))($source, $fixed);
        // Whatever a test asks, the form gives every component what it takes.
        ComponentContracts::assertHonoured($form);

        return $form;
    }
}
