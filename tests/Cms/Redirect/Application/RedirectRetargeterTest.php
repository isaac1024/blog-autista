<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\RedirectNotFound;
use Cms\Redirect\Application\RedirectRetargeter;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Redirect\Domain\RedirectIdMother;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\InvalidUuid;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * A redirect leads somewhere else, or says its address is gone, holding its
 * address and the new target, and read only then, so it changes what the
 * last change left. Its address never changes. The site has the pages
 * RedirectMother::PAGE and OTHER on it, and the image RedirectMother::IMAGE.
 */
final class RedirectRetargeterTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string ID = RedirectIdMother::DEFAULT_VALUE;
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string CREATED = '2026-10-01 09:00:00';

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    #[DataProvider('retargets')]
    public function testItLeadsTheRedirectAsItIsOnceItHoldsItsAddressAndSavesIt(TargetInput $target, Redirect $expected): void
    {
        $mutualExclusion = new RecordingMutualExclusion();
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())->method('sourceOf')->with(self::equalTo(RedirectIdMother::create(self::ID)))->willReturn(Source::fromStored('/vieja.html'));
        $repository->expects(self::once())
            ->method('find')
            ->with(self::equalTo(RedirectIdMother::create(self::ID)))
            ->willReturnCallback(static fn (): Redirect => $mutualExclusion->isHolding() ? self::stored() : self::fail('Read before holding its address.'));
        $repository->expects(self::once())->method('save')->with(self::equalTo($expected));

        new RedirectRetargeter(self::ID, $target)($repository, self::site(), $mutualExclusion);
    }

    /**
     * @return iterable<string, array{TargetInput, Redirect}>
     */
    public static function retargets(): iterable
    {
        yield 'another page' => [new TargetInput('page', self::OTHER), RedirectMother::create(id: self::ID, target: Resource::page(self::OTHER), createdAt: self::CREATED, updatedAt: ClockMother::NOW)];
        yield 'nowhere' => [new TargetInput(null, null), RedirectMother::gone(id: self::ID, createdAt: self::CREATED, updatedAt: ClockMother::NOW)];
        yield 'where it leads already, which changes nothing' => [new TargetInput('page', RedirectMother::PAGE), self::stored()];
    }

    public function testItHoldsTheAddressAndTheNewTarget(): void
    {
        $mutualExclusion = new RecordingMutualExclusion();
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('sourceOf')->willReturn(Source::fromStored('/vieja.html'));
        $repository->method('find')->willReturn(self::stored());

        new RedirectRetargeter(self::ID, new TargetInput('page', self::OTHER))($repository, self::site(), $mutualExclusion);

        self::assertSame(['address:/vieja.html', 'page:'.self::OTHER], $mutualExclusion->lastHeld());
    }

    /**
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('refused')]
    public function testItTellsWhyItRefusesATargetAndSavesNothing(TargetInput $target, string $key, array $parameters): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('sourceOf')->willReturn(Source::fromStored('/vieja.html'));
        $repository->method('find')->willReturn(self::stored());
        $repository->expects(self::never())->method('save');

        self::assertTells($key, $parameters, static fn () => new RedirectRetargeter(self::ID, $target)($repository, self::site(), new RecordingMutualExclusion()));
    }

    /**
     * @return iterable<string, array{TargetInput, string, array<string, int|string>}>
     */
    public static function refused(): iterable
    {
        yield 'a page off the site' => [new TargetInput('page', RedirectMother::OFF_SITE), 'errors.redirect.target_off_site', []];
        yield 'an image for the address of a page' => [new TargetInput('image', RedirectMother::IMAGE), 'errors.redirect.other_to_image', []];
        yield 'no page chosen' => [new TargetInput('page', ''), 'errors.redirect.missing_target', []];
        yield 'a kind of target there is none of' => [new TargetInput('script', self::OTHER), 'errors.redirect.unknown_target_type', ['%type%' => 'script']];
    }

    public function testItRefusesToLeadTheAddressOfAnImageToAPage(): void
    {
        $image = RedirectMother::create(id: self::ID, source: '/media/2026/11/0196f3a0-0000-7000-8000-0000000000c3', target: Resource::image(RedirectMother::IMAGE));
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('sourceOf')->willReturn($image->source());
        $repository->method('find')->willReturn($image);
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.redirect.image_to_other', [], static fn () => new RedirectRetargeter(self::ID, new TargetInput('page', self::OTHER))($repository, self::site(), new RecordingMutualExclusion()));
    }

    public function testItThrowsWhenTheRedirectDoesNotExist(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('sourceOf')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(RedirectNotFound::withId(self::ID));

        new RedirectRetargeter(self::ID, new TargetInput(null, null))($repository, self::site(), new RecordingMutualExclusion());
    }

    /**
     * Another change deleted it while this one waited for its address.
     */
    public function testItThrowsWhenTheRedirectIsGoneOnceItHoldsItsAddress(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('sourceOf')->willReturn(Source::fromStored('/vieja.html'));
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectExceptionObject(RedirectNotFound::withId(self::ID));

        new RedirectRetargeter(self::ID, new TargetInput(null, null))($repository, self::site(), new RecordingMutualExclusion());
    }

    public function testItRejectsAnIdThatIsNotAUuid(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::never())->method('sourceOf');

        $this->expectExceptionObject(InvalidUuid::withValue('redirect-id'));

        new RedirectRetargeter('redirect-id', new TargetInput(null, null))($repository, self::site(), new RecordingMutualExclusion());
    }

    /**
     * The redirect as another change left it: from /vieja.html to the page.
     */
    private static function stored(): Redirect
    {
        return RedirectMother::create(id: self::ID, createdAt: self::CREATED);
    }

    private static function site(): SiteResources
    {
        $site = self::createStub(SiteResources::class);
        $site->method('isOnSite')->willReturnCallback(static fn (Resource $resource): bool => \in_array($resource->key(), ['page:'.RedirectMother::PAGE, 'page:'.self::OTHER, 'image:'.RedirectMother::IMAGE], true));

        return $site;
    }
}
