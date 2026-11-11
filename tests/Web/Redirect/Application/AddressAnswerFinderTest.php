<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Web\Redirect\Application\AddressAnswer;
use Web\Redirect\Application\AddressAnswerFinder;
use Web\Redirect\Domain\Redirect;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Domain\TargetAddresses;
use Web\Tests\Redirect\Domain\RedirectMother;

/**
 * What the site answers at an address it has nothing of its own at: where
 * its redirect leads now, that it is gone, or nothing, however the address
 * is asked for; every copy of an image leads to the same copy of its target.
 */
final class AddressAnswerFinderTest extends TestCase
{
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';

    public function testItLeadsWhereTheTargetOfTheRedirectIsNow(): void
    {
        self::assertEquals(AddressAnswer::movedTo('/destino.html'), $this->answer('/vieja.html', ['/vieja.html' => RedirectMother::create(targetId: self::PAGE)]));
    }

    public function testItSaysTheAddressIsGoneWhenItsRedirectLeadsNowhere(): void
    {
        self::assertEquals(AddressAnswer::gone(), $this->answer('/vieja.html', ['/vieja.html' => RedirectMother::gone()]));
    }

    /**
     * Nothing is answered at an address without a redirect, at one whose
     * target is off the site, nor at one no redirect could ever be kept at.
     *
     * @param array<string, Redirect> $redirects
     */
    #[DataProvider('nothing')]
    public function testItAnswersNothingWhereNothingLeads(string $path, array $redirects): void
    {
        self::assertNull($this->answer($path, $redirects));
    }

    /**
     * @return iterable<string, array{string, array<string, Redirect>}>
     */
    public static function nothing(): iterable
    {
        yield 'an address without a redirect' => ['/vieja.html', ['/otra.html' => RedirectMother::create(targetId: self::PAGE)]];
        yield 'a target off the site' => ['/vieja.html', ['/vieja.html' => RedirectMother::create(targetId: '0196f3a0-0000-7000-8000-0000000000ff')]];
        yield 'an address longer than any redirect has' => ['/'.str_repeat('a', 300).'.html', []];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sameAddresses(): iterable
    {
        yield 'as it is kept' => ['/gu%c3%ada-antigua.html'];
        yield 'with capitals' => ['/Guía-Antigua.html'];
        yield 'all in capitals, accents too' => ['/GUÍA-ANTIGUA.html'];
        yield 'encoded in capitals' => ['/gu%C3%ADa-antigua.html'];
        yield 'through a dot segment' => ['/blog/../guía-antigua.html'];
    }

    #[DataProvider('sameAddresses')]
    public function testItAnswersAnAddressHoweverItIsAskedFor(string $path): void
    {
        self::assertEquals(AddressAnswer::movedTo('/destino.html'), $this->answer($path, ['/gu%c3%ada-antigua.html' => RedirectMother::create(targetId: self::PAGE)]));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function widths(): iterable
    {
        yield 'the smallest' => [400];
        yield 'the middle one' => [800];
        yield 'the largest' => [1200];
    }

    /**
     * Every copy of an image is answered by the redirect of its file, and
     * leads to the copy of the target at the same width.
     */
    #[DataProvider('widths')]
    public function testItLeadsEveryCopyOfAnImageToTheSameWidthOfTheTarget(int $width): void
    {
        $answer = $this->answer(
            \sprintf('/media/2026/11/0196f3a0-0000-7000-8000-0000000000c9-%dw.webp', $width),
            ['/media/2026/11/0196f3a0-0000-7000-8000-0000000000c9' => RedirectMother::create(targetType: 'image', targetId: self::IMAGE)],
        );

        self::assertEquals(AddressAnswer::movedTo(\sprintf('/media/2026/11/%s-%dw.webp', self::IMAGE, $width)), $answer);
    }

    /**
     * @param array<string, Redirect> $redirects keyed by the address each answers
     */
    private function answer(string $path, array $redirects): ?AddressAnswer
    {
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('findBySource')->willReturnCallback(static fn (string $source): ?Redirect => $redirects[$source] ?? null);
        $addresses = self::createStub(TargetAddresses::class);
        $addresses->method('addressOf')->willReturnCallback(static fn (string $type, string $id, ?int $width): ?string => match (true) {
            'page' === $type && self::PAGE === $id => '/destino.html',
            'image' === $type && self::IMAGE === $id => \sprintf('/media/2026/11/%s-%dw.webp', $id, $width ?? 1200),
            default => null,
        });

        return new AddressAnswerFinder($path)($repository, $addresses);
    }
}
