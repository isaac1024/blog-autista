<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\PreviousAddressDecision;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Infrastructure\Controller\PublicationRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the form that confirms publishing sends: the fingerprint of what it
 * showed, and what the address the page leaves does.
 */
final class PublicationRequestTest extends TestCase
{
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000d1';
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000d2';

    /**
     * Straight from the editor, nothing was shown; an empty fingerprint is
     * a confirmation of nothing told.
     *
     * @param array<string, string> $posted
     */
    #[DataProvider('outlooks')]
    public function testItReadsTheFingerprintOfWhatTheFormShowed(array $posted, ?string $outlook): void
    {
        self::assertSame($outlook, PublicationRequest::outlook(new Request(request: $posted)));
    }

    /**
     * @return iterable<string, array{array<string, string>, ?string}>
     */
    public static function outlooks(): iterable
    {
        yield 'none, from the editor' => [[], null];
        yield 'of nothing told' => [['outlook' => ''], ''];
        yield 'of what was told' => [['outlook' => 'abc'], 'abc'];
    }

    /**
     * @param array<string, string> $previous
     */
    #[DataProvider('previous')]
    public function testItReadsWhatTheAddressThePageLeavesDoes(array $previous, PreviousAddressDecision $expected): void
    {
        self::assertEquals($expected, PublicationRequest::previous(new Request(request: ['previous' => $previous]), Resource::page(self::PAGE)));
    }

    /**
     * @return iterable<string, array{array<string, string>, PreviousAddressDecision}>
     */
    public static function previous(): iterable
    {
        yield 'nothing said' => [[], PreviousAddressDecision::undecided()];
        yield 'kept as it is' => [['action' => 'keep'], PreviousAddressDecision::keep()];
        yield 'led to the page itself' => [['action' => 'itself'], PreviousAddressDecision::leadTo(new TargetInput('page', self::PAGE))];
        yield 'led to another page' => [['action' => 'page', 'page_id' => self::OTHER], PreviousAddressDecision::leadTo(new TargetInput('page', self::OTHER))];
        yield 'gone' => [['action' => 'gone'], PreviousAddressDecision::leadTo(new TargetInput(null, null))];
    }
}
