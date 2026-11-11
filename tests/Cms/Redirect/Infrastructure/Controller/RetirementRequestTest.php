<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\IncomingDecision;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\Target;
use Cms\Redirect\Infrastructure\Controller\RetirementRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\DomainErrorAssertions;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the form that takes a resource off the site sends: what its own
 * address does, and what each redirect to it does instead.
 */
final class RetirementRequestTest extends TestCase
{
    use DomainErrorAssertions;

    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string IMAGE = '0196f3a0-0000-7000-8000-0000000000c1';

    /**
     * @param array<string, string> $address
     */
    #[DataProvider('addresses')]
    public function testItReadsWhatItsAddressDoes(array $address, ?TargetInput $expected): void
    {
        self::assertEquals($expected, RetirementRequest::address(new Request(request: ['address' => $address])));
    }

    /**
     * @return iterable<string, array{array<string, string>, ?TargetInput}>
     */
    public static function addresses(): iterable
    {
        yield 'not found' => [['action' => 'none'], null];
        yield 'gone' => [['action' => 'gone'], new TargetInput(null, null)];
        yield 'a page, not the image also picked' => [['action' => 'page', 'page_id' => self::PAGE, 'image_id' => self::IMAGE], new TargetInput('page', self::PAGE)];
        yield 'an image' => [['action' => 'image', 'image_id' => self::IMAGE], new TargetInput('image', self::IMAGE)];
    }

    public function testItRefusesAnAddressWithNothingChosen(): void
    {
        self::assertTells('errors.redirect.undecided_address', [], static fn () => RetirementRequest::address(new Request(request: ['address' => ['action' => '']])));
        self::assertTells('errors.redirect.undecided_address', [], static fn () => RetirementRequest::address(new Request()));
    }

    /**
     * Each redirect is decided by its id; one with nothing chosen is left
     * out, as one not sent at all.
     */
    public function testItReadsWhatEachRedirectToItDoesByItsId(): void
    {
        $request = new Request(request: ['incoming' => [
            'e1' => ['action' => 'same'],
            'e2' => ['action' => 'delete'],
            'e3' => ['action' => 'gone'],
            'e4' => ['action' => 'page', 'page_id' => self::PAGE],
            'e5' => ['action' => ''],
            'e6' => 'not a field',
            '7' => ['action' => 'delete'],
        ]]);

        self::assertEquals([
            'e1' => IncomingDecision::sameAsAddress(),
            'e2' => IncomingDecision::delete(),
            'e3' => IncomingDecision::leadingTo(Target::gone()),
            'e4' => IncomingDecision::leadingTo(Target::leadingTo(Resource::page(self::PAGE))),
            '7' => IncomingDecision::delete(),
        ], RetirementRequest::incoming($request));
        self::assertSame(['e1', 'e2', 'e3', 'e4', '7'], array_map(strval(...), array_keys(RetirementRequest::incoming($request))));
    }

    public function testItReadsNoDecisionWhenNoneIsSent(): void
    {
        self::assertSame([], RetirementRequest::incoming(new Request()));
    }

    public function testItRefusesARedirectLedToNothingPicked(): void
    {
        self::assertTells('errors.redirect.missing_target', [], static fn () => RetirementRequest::incoming(new Request(request: ['incoming' => ['e1' => ['action' => 'page']]])));
    }
}
