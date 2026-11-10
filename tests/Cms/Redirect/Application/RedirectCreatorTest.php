<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Image\Domain\ImageRepository;
use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Image\Domain\ImageMother;
use Cms\Tests\Page\Domain\PageMother;
use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Domain\MutualExclusion;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A redirect is created from an absolute address of the site, written one
 * way only, to a resource on the site, or saying the address is gone; never
 * at an address the site answers itself, nor to anything off the site. It
 * answers the CMS as it is, so the test keeps a page on the site, at
 * /sobre-mi.html, one off it, last at /retirada.html, and an image on the
 * site.
 */
final class RedirectCreatorTest extends KernelTestCase
{
    use DomainErrorAssertions;

    private const string SITE = 'https://www.blogautista.localhost';
    private const string HOST = 'www.blogautista.localhost';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string OFF_SITE = '0196f3a0-0000-7000-8000-0000000000a2';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c2';

    #[\Override]
    protected function setUp(): void
    {
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: self::PAGE, title: 'Sobre mí', slug: 'sobre-mi'));
        $pages->save(PageMother::unpublished(id: self::OFF_SITE, title: 'Retirada', slug: 'retirada'));
        self::getContainer()->get(ImageRepository::class)->save(ImageMother::published(id: self::IMAGE));
    }

    public function testItCreatesARedirectToAPage(): void
    {
        $this->create(self::SITE.'/vieja.html', new TargetInput('page', self::PAGE));

        $redirect = $this->redirectAt('/vieja.html');
        self::assertSame(self::PAGE, $redirect?->target()->resource()?->id());
        self::assertNull($redirect->origin()->resource());
    }

    /**
     * While it creates the redirect, it holds the address and the target, so
     * neither another redirect there nor taking the target off slips in.
     */
    public function testItHoldsTheAddressAndTheTargetWhileItCreatesTheRedirect(): void
    {
        $mutualExclusion = new RecordingMutualExclusion();

        $this->create(self::SITE.'/vieja.html', new TargetInput('page', self::PAGE), $mutualExclusion);

        self::assertSame(['address:/vieja.html', 'page:'.self::PAGE], $mutualExclusion->lastHeld());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writtenDifferently(): iterable
    {
        yield 'with accents and capitals' => [self::SITE.'/Guía Antigua.html'];
        yield 'all in capitals, accents too' => [self::SITE.'/GUÍA ANTIGUA.html'];
        yield 'encoded' => [self::SITE.'/gu%C3%ADa%20antigua.html'];
        yield 'through dot segments' => [self::SITE.'/blog/./../guía antigua.html'];
        yield 'with a query and a fragment' => [self::SITE.'/guía antigua.html?ref=boletin#inicio'];
        yield 'with spaces around' => ['  '.self::SITE.'/guía antigua.html  '];
        yield 'with a scheme and a host in capitals' => ['HTTPS://WWW.BLOGAUTISTA.LOCALHOST/guía antigua.html'];
    }

    /**
     * @return iterable<string, array{string, TargetInput, string, array<string, int|string>}>
     */
    public static function refused(): iterable
    {
        $longest = '/'.str_repeat('a', 249).'.html';

        yield 'an address of another site' => ['https://otra.web/vieja.html', new TargetInput(null, null), 'errors.site_path.other_host', ['%url%' => 'https://otra.web/vieja.html', '%host%' => 'www.blogautista.localhost']];
        yield 'an address with no host' => ['/vieja.html', new TargetInput(null, null), 'errors.site_path.not_absolute', ['%url%' => '/vieja.html']];
        yield 'an address that is not one' => ['http:///vieja.html', new TargetInput(null, null), 'errors.site_path.not_absolute', ['%url%' => 'http:///vieja.html']];
        yield 'an address of no web' => ['mailto:hola@blogautista.localhost', new TargetInput(null, null), 'errors.site_path.not_absolute', ['%url%' => 'mailto:hola@blogautista.localhost']];
        yield 'the home page' => [self::SITE.'/', new TargetInput(null, null), 'errors.site_path.root', []];
        yield 'an address too long' => [self::SITE.$longest.'x', new TargetInput(null, null), 'errors.site_path.too_long', ['%max%' => 255]];
        yield 'a page on the site' => [self::SITE.'/sobre-mi.html', new TargetInput(null, null), 'errors.redirect.live_source', ['%source%' => '/sobre-mi.html']];
        yield 'an image on the site' => [self::SITE.'/media/2026/11/'.self::IMAGE.'-400w.webp', new TargetInput(null, null), 'errors.redirect.live_source', ['%source%' => '/media/2026/11/'.self::IMAGE.'-1200w.webp']];
        yield 'a page off the site' => [self::SITE.'/vieja.html', new TargetInput('page', self::OFF_SITE), 'errors.redirect.target_off_site', []];
        yield 'a page that does not exist' => [self::SITE.'/vieja.html', new TargetInput('page', '0196f3a0-0000-7000-8000-0000000000ff'), 'errors.redirect.target_off_site', []];
        yield 'an image that does not exist' => [self::SITE.'/media/2026/11/0196f3a0-0000-7000-8000-0000000000c1-800w.webp', new TargetInput('image', '0196f3a0-0000-7000-8000-0000000000ff'), 'errors.redirect.target_off_site', []];
        yield 'no page chosen' => [self::SITE.'/vieja.html', new TargetInput('page', ''), 'errors.redirect.missing_target', []];
        yield 'no target at all' => [self::SITE.'/vieja.html', new TargetInput('page', null), 'errors.redirect.missing_target', []];
        yield 'a kind of target there is none of' => [self::SITE.'/vieja.html', new TargetInput('script', self::PAGE), 'errors.redirect.unknown_target_type', ['%type%' => 'script']];
        yield 'an image for the address of a page' => [self::SITE.'/vieja.html', new TargetInput('image', self::IMAGE), 'errors.redirect.other_to_image', []];
        yield 'a page for the address of an image' => [self::SITE.'/media/2026/11/0196f3a0-0000-7000-8000-0000000000c1-800w.webp', new TargetInput('page', self::PAGE), 'errors.redirect.image_to_other', []];
        yield 'an address under /media/ of no image' => [self::SITE.'/media/logo.png', new TargetInput(null, null), 'errors.redirect.not_an_image_copy', ['%url%' => self::SITE.'/media/logo.png']];
    }

    /**
     * An address is kept as browsers ask for it, so its redirect is found
     * however it was written.
     */
    #[DataProvider('writtenDifferently')]
    public function testItKeepsTheAddressAsBrowsersAskForIt(string $url): void
    {
        $this->create($url, new TargetInput(null, null));

        self::assertNotNull($this->redirectAt('/gu%c3%ada%20antigua.html'));
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('refused')]
    public function testItTellsWhyItRefusesARedirect(string $url, TargetInput $target, string $key, array $parameters): void
    {
        self::assertTells($key, $parameters, fn () => $this->create($url, $target));
    }

    public function testItTakesAnAddressAsLongAsItCanBe(): void
    {
        $longest = '/'.str_repeat('a', 249).'.html';

        $this->create(self::SITE.$longest, new TargetInput(null, null));

        self::assertNotNull($this->redirectAt($longest));
    }

    public function testItRefusesASecondRedirectAtTheSameAddress(): void
    {
        $this->create(self::SITE.'/vieja.html', new TargetInput(null, null));

        self::assertTells('errors.redirect.source_taken', ['%source%' => '/vieja.html'], fn () => $this->create(self::SITE.'/Vieja.html', new TargetInput('page', self::PAGE)));
        self::assertNull($this->redirectAt('/vieja.html')?->target()->resource());
    }

    /**
     * Created at the last address of a page off the site, a redirect knows
     * that page as its origin, so what links to it follows the redirect.
     */
    public function testItKnowsThePageOffTheSiteWhoseLastAddressItAnswers(): void
    {
        $this->create(self::SITE.'/retirada.html', new TargetInput('page', self::PAGE));

        self::assertSame(self::OFF_SITE, $this->redirectAt('/retirada.html')?->origin()->resource()?->id());
    }

    private function create(string $url, TargetInput $target, ?MutualExclusion $mutualExclusion = null): void
    {
        new RedirectCreator(RedirectId::random()->value(), $url, $target, self::HOST)(
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(SiteResources::class),
            $mutualExclusion ?? self::getContainer()->get(MutualExclusion::class),
        );
    }

    private function redirectAt(string $path): ?Redirect
    {
        return self::getContainer()->get(RedirectRepository::class)->findBySource(Source::fromPath($path));
    }
}
