<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\MutualExclusion;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * A redirect is created from an absolute address of the site, written one
 * way only, to a resource on the site, or saying the address is gone; never
 * at an address the site answers itself, nor to anything off the site. The
 * site has a page on it at /sobre-mi.html, the page RedirectMother::PAGE and
 * the image RedirectMother::IMAGE; RedirectMother::OFF_SITE is off it, last
 * at /retirada.html.
 */
final class RedirectCreatorTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string SITE = 'https://www.blogautista.localhost';
    private const string HOST = 'www.blogautista.localhost';
    private const string ID = RedirectIdMother::DEFAULT_VALUE;
    private const string PAGE = RedirectMother::PAGE;
    private const string IMAGE = RedirectMother::IMAGE;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItCreatesARedirectToAPage(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::create(id: self::ID, source: '/vieja.html', target: Resource::page(self::PAGE))));

        self::create(self::SITE.'/vieja.html', new TargetInput('page', self::PAGE), $repository);
    }

    public function testItCreatesARedirectThatSaysTheAddressIsGone(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::gone(id: self::ID, source: '/vieja.html')));

        self::create(self::SITE.'/vieja.html', new TargetInput(null, null), $repository);
    }

    /**
     * Every copy of an image shares the address of its file, so the redirect
     * leads each to the same copy of the image it leads to.
     */
    public function testItCreatesARedirectFromEveryCopyOfAnImageToAnother(): void
    {
        $other = '0196f3a0-0000-7000-8000-0000000000c2';
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::create(id: self::ID, source: '/media/2026/11/'.$other, target: Resource::image(self::IMAGE))));

        self::create(self::SITE.'/media/2026/11/'.$other.'-400w.webp', new TargetInput('image', self::IMAGE), $repository);
    }

    /**
     * While it creates the redirect, it holds the address and the target, so
     * neither another redirect there nor taking the target off slips in.
     */
    public function testItHoldsTheAddressAndTheTargetWhileItCreatesTheRedirect(): void
    {
        $mutualExclusion = new RecordingMutualExclusion();

        self::create(self::SITE.'/vieja.html', new TargetInput('page', self::PAGE), self::createStub(RedirectRepository::class), $mutualExclusion);

        self::assertSame(['address:/vieja.html', 'page:'.self::PAGE], $mutualExclusion->lastHeld());
    }

    /**
     * An address is kept as browsers ask for it, so its redirect is found
     * however it was written.
     */
    #[DataProvider('writtenDifferently')]
    public function testItKeepsTheAddressAsBrowsersAskForIt(string $url): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::gone(id: self::ID, source: '/gu%c3%ada%20antigua.html')));

        self::create($url, new TargetInput(null, null), $repository);
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

    public function testItTakesAnAddressAsLongAsItCanBe(): void
    {
        $longest = '/'.str_repeat('a', 249).'.html';
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::gone(id: self::ID, source: $longest)));

        self::create(self::SITE.$longest, new TargetInput(null, null), $repository);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('refused')]
    public function testItTellsWhyItRefusesARedirectAndSavesNothing(string $url, TargetInput $target, string $key, array $parameters): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);
        $repository->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => self::create($url, $target, $repository));
    }

    /**
     * @return iterable<string, array{string, TargetInput, string, array<string, int|string>}>
     */
    public static function refused(): iterable
    {
        $longest = '/'.str_repeat('a', 249).'.html';
        $gone = new TargetInput(null, null);

        yield 'an address of another site' => ['https://otra.web/vieja.html', $gone, 'errors.site_path.other_host', ['%url%' => 'https://otra.web/vieja.html', '%host%' => self::HOST]];
        yield 'an address with no host' => ['/vieja.html', $gone, 'errors.site_path.not_absolute', ['%url%' => '/vieja.html']];
        yield 'an address that is not one' => ['http:///vieja.html', $gone, 'errors.site_path.not_absolute', ['%url%' => 'http:///vieja.html']];
        yield 'an address of no web' => ['mailto:hola@blogautista.localhost', $gone, 'errors.site_path.not_absolute', ['%url%' => 'mailto:hola@blogautista.localhost']];
        yield 'the home page' => [self::SITE.'/', $gone, 'errors.site_path.root', []];
        yield 'an address too long' => [self::SITE.$longest.'x', $gone, 'errors.site_path.too_long', ['%max%' => 255]];
        yield 'a page on the site' => [self::SITE.'/sobre-mi.html', $gone, 'errors.redirect.live_source', ['%source%' => '/sobre-mi.html']];
        yield 'an image on the site' => [self::SITE.'/media/2026/11/'.self::IMAGE.'-400w.webp', $gone, 'errors.redirect.live_source', ['%source%' => '/media/2026/11/'.self::IMAGE.'-1200w.webp']];
        yield 'a page off the site' => [self::SITE.'/vieja.html', new TargetInput('page', RedirectMother::OFF_SITE), 'errors.redirect.target_off_site', []];
        yield 'an image off the site' => [self::SITE.'/media/2026/11/0196f3a0-0000-7000-8000-0000000000c3-800w.webp', new TargetInput('image', '0196f3a0-0000-7000-8000-0000000000ff'), 'errors.redirect.target_off_site', []];
        yield 'no page chosen' => [self::SITE.'/vieja.html', new TargetInput('page', ''), 'errors.redirect.missing_target', []];
        yield 'no target at all' => [self::SITE.'/vieja.html', new TargetInput('page', null), 'errors.redirect.missing_target', []];
        yield 'a kind of target there is none of' => [self::SITE.'/vieja.html', new TargetInput('script', self::PAGE), 'errors.redirect.unknown_target_type', ['%type%' => 'script']];
        yield 'a target that is no id' => [self::SITE.'/vieja.html', new TargetInput('page', 'sobre-mi'), 'errors.uuid.invalid', ['%value%' => 'sobre-mi']];
        yield 'an image for the address of a page' => [self::SITE.'/vieja.html', new TargetInput('image', self::IMAGE), 'errors.redirect.other_to_image', []];
        yield 'a page for the address of an image' => [self::SITE.'/media/2026/11/0196f3a0-0000-7000-8000-0000000000c3-800w.webp', new TargetInput('page', self::PAGE), 'errors.redirect.image_to_other', []];
        yield 'an address under /media/ of no image' => [self::SITE.'/media/logo.png', $gone, 'errors.redirect.not_an_image_copy', ['%url%' => self::SITE.'/media/logo.png']];
    }

    public function testItRefusesASecondRedirectAtTheSameAddress(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('findBySource')
            ->with(self::equalTo(Source::fromStored('/vieja.html')))
            ->willReturn(RedirectMother::gone(id: '0196f3a0-0000-7000-8000-0000000000e9'));
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.redirect.source_taken', ['%source%' => '/vieja.html'], static fn () => self::create(self::SITE.'/Vieja.html', new TargetInput('page', self::PAGE), $repository));
    }

    /**
     * Created at the last address of a page off the site, a redirect knows
     * that page as its origin, so what links to it follows the redirect.
     */
    public function testItKnowsThePageOffTheSiteWhoseLastAddressItAnswers(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::create(id: self::ID, source: '/retirada.html', origin: Resource::page(RedirectMother::OFF_SITE))));

        self::create(self::SITE.'/retirada.html', new TargetInput('page', self::PAGE), $repository);
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.uuid.invalid', ['%value%' => 'redirect-id'], static fn () => new RedirectCreator('redirect-id', self::SITE.'/vieja.html', new TargetInput(null, null), self::HOST)(
            $repository,
            self::site(),
            new RecordingMutualExclusion(),
        ));
    }

    private static function create(string $url, TargetInput $target, RedirectRepository $repository, ?MutualExclusion $mutualExclusion = null): void
    {
        new RedirectCreator(self::ID, $url, $target, self::HOST)($repository, self::site(), $mutualExclusion ?? new RecordingMutualExclusion());
    }

    /**
     * The site as the class comment tells it.
     */
    private static function site(): SiteResources
    {
        $site = self::createStub(SiteResources::class);
        $site->method('isLive')->willReturnCallback(static fn (Source $source): bool => \in_array($source->value(), ['/sobre-mi.html', '/media/2026/11/'.self::IMAGE], true));
        $site->method('isOnSite')->willReturnCallback(static fn (Resource $resource): bool => $resource->equals(Resource::page(self::PAGE)) || $resource->equals(Resource::image(self::IMAGE)));
        $site->method('lastAt')->willReturnCallback(static fn (Source $source): ?Resource => '/retirada.html' === $source->value() ? Resource::page(RedirectMother::OFF_SITE) : null);

        return $site;
    }
}
