<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\ResourceRetirement;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\IncomingDecision;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Redirect\Domain\Target;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Taking a page off the site decides first what every redirect to it does,
 * since none may lead to a page off the site, then what its own address
 * does. The page is at /sobre-mi.html, and the redirect from /vieja.html
 * leads to it; the pages OTHER and THIRD are on the site, OFF_SITE isn't.
 */
final class ResourceRetirementTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000d1';
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000b1';
    private const string THIRD = '0196f3a0-0000-7000-8000-0000000000b2';
    private const string OFF_SITE = RedirectMother::OFF_SITE;
    private const string INCOMING = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string SECOND = '0196f3a0-0000-7000-8000-0000000000e2';
    private const string NEW_REDIRECT = '0196f3a0-0000-7000-8000-0000000000f1';
    private const string CREATED = '2026-10-01 09:00:00';

    private bool $unpublished = false;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    /**
     * The redirects to it lead where its address does, and its address gets
     * a redirect of its own, knowing the page as its origin.
     */
    public function testItLeadsTheRedirectsToItWhereItsAddressLeadsAndTakesItOff(): void
    {
        $repository = $this->repository([self::incoming(self::INCOMING, '/vieja.html')]);
        $saved = [];
        $repository->expects(self::exactly(2))->method('save')->willReturnCallback(static function (Redirect $redirect) use (&$saved): void {
            $saved[] = $redirect;
        });

        $this->retire($repository, new TargetInput('page', self::OTHER), [self::INCOMING => IncomingDecision::sameAsAddress()]);

        self::assertTrue($this->unpublished);
        self::assertEquals([
            RedirectMother::create(id: self::INCOMING, source: '/vieja.html', target: Resource::page(self::OTHER), createdAt: self::CREATED, updatedAt: ClockMother::NOW),
            RedirectMother::create(id: self::NEW_REDIRECT, source: '/sobre-mi.html', target: Resource::page(self::OTHER), origin: Resource::page(self::PAGE)),
        ], $saved);
    }

    public function testItDeletesOrLeadsEachRedirectToItAsDecidedAndLeavesItsAddressNotFound(): void
    {
        $deleted = self::incoming(self::INCOMING, '/vieja.html');
        $repository = $this->repository([$deleted, self::incoming(self::SECOND, '/otra-vieja.html')]);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($deleted));
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::gone(id: self::SECOND, source: '/otra-vieja.html', createdAt: self::CREATED, updatedAt: ClockMother::NOW)));

        $this->retire($repository, null, [self::INCOMING => IncomingDecision::delete(), self::SECOND => IncomingDecision::leadingTo(Target::gone())]);

        self::assertTrue($this->unpublished);
    }

    public function testItSaysItsAddressIsGoneWhenTold(): void
    {
        $repository = $this->repository([]);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::gone(id: self::NEW_REDIRECT, source: '/sobre-mi.html', origin: Resource::page(self::PAGE))));

        $this->retire($repository, new TargetInput(null, null), []);
    }

    /**
     * Its address has one redirect at most: one there already refuses the
     * one it would get.
     */
    public function testItRefusesToLeadItsAddressWhenAnotherRedirectIsThere(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(RedirectMother::gone(id: self::SECOND, source: '/sobre-mi.html'));
        $repository->method('findTargeting')->willReturn([]);
        $repository->method('sourcesTargeting')->willReturn([]);
        $repository->expects(self::never())->method('save');

        self::assertTells('errors.redirect.source_taken', ['%source%' => '/sobre-mi.html'], fn () => $this->retire($repository, new TargetInput('page', self::OTHER), []));
    }

    /**
     * Without an address on the site, there is none to lead.
     */
    public function testItLeadsNoAddressWhenThePageHasNone(): void
    {
        $repository = $this->repository([]);
        $repository->expects(self::never())->method('save');

        $this->retire($repository, new TargetInput('page', self::OTHER), [], address: null);

        self::assertTrue($this->unpublished);
    }

    /**
     * @param array<string, IncomingDecision> $incoming
     * @param array<string, int|string>       $parameters
     */
    #[DataProvider('refused')]
    public function testItTellsWhyItRefusesTheChoicesAndChangesNothing(?TargetInput $address, array $incoming, string $key, array $parameters): void
    {
        $repository = $this->repository([self::incoming(self::INCOMING, '/vieja.html')]);
        $repository->expects(self::never())->method('save');
        $repository->expects(self::never())->method('delete');

        self::assertTells($key, $parameters, fn () => $this->retire($repository, $address, $incoming));
        self::assertFalse($this->unpublished);
    }

    /**
     * @return iterable<string, array{?TargetInput, array<string, IncomingDecision>, string, array<string, int|string>}>
     */
    public static function refused(): iterable
    {
        $itself = Target::leadingTo(Resource::page(self::PAGE));

        yield 'a redirect to it undecided' => [new TargetInput('page', self::OTHER), [], 'errors.redirect.undecided', ['%source%' => '/vieja.html']];
        yield 'a decision of another redirect only' => [new TargetInput('page', self::OTHER), [self::SECOND => IncomingDecision::delete()], 'errors.redirect.undecided', ['%source%' => '/vieja.html']];
        yield 'a redirect to it led to itself' => [null, [self::INCOMING => IncomingDecision::leadingTo($itself)], 'errors.redirect.to_itself', []];
        yield 'its address led to itself' => [new TargetInput('page', self::PAGE), [self::INCOMING => IncomingDecision::delete()], 'errors.redirect.to_itself', []];
        yield 'a redirect to it following an address left not found' => [null, [self::INCOMING => IncomingDecision::sameAsAddress()], 'errors.redirect.no_new_target', []];
        yield 'a redirect to it following an address that is gone' => [new TargetInput(null, null), [self::INCOMING => IncomingDecision::sameAsAddress()], 'errors.redirect.no_new_target', []];
        yield 'a redirect to it led off the site' => [null, [self::INCOMING => IncomingDecision::leadingTo(Target::leadingTo(Resource::page(self::OFF_SITE)))], 'errors.redirect.target_off_site', []];
    }

    /**
     * Its own address is led last, once the page is off the site, so a
     * refusal then comes after the page and the redirects to it changed:
     * the transaction undoes it all.
     */
    public function testItLeadsItsOwnAddressLastWithinTheTransaction(): void
    {
        $transaction = new RecordingTransaction();
        $repository = $this->repository([self::incoming(self::INCOMING, '/vieja.html')]);
        $repository->expects(self::once())->method('save')->willReturnCallback(static fn () => $transaction->isRunning() ? null : self::fail('Saved outside the transaction.'));

        self::assertTells('errors.redirect.target_off_site', [], fn () => new ResourceRetirement(
            self::NEW_REDIRECT,
            Resource::page(self::PAGE),
            new TargetInput('page', self::OFF_SITE),
            [self::INCOMING => IncomingDecision::leadingTo(Target::gone())],
            function () use ($transaction): void {
                $this->unpublished = $transaction->isRunning();
            },
        )($repository, self::site('/sobre-mi.html'), new RecordingMutualExclusion(), $transaction));
        self::assertTrue($this->unpublished, 'Taken off within the transaction.');
    }

    /**
     * It holds the page, its address and where it will lead, the address of
     * every redirect to it, and where each will lead.
     */
    public function testItHoldsThePageItsAddressAndEveryRedirectToItWhileTakingItOff(): void
    {
        $mutualExclusion = new RecordingMutualExclusion();
        $repository = $this->repository([self::incoming(self::INCOMING, '/vieja.html'), self::incoming(self::SECOND, '/otra-vieja.html')]);
        // Both redirects to it, then the one of its own address.
        $repository->expects(self::exactly(3))->method('save');

        new ResourceRetirement(
            self::NEW_REDIRECT,
            Resource::page(self::PAGE),
            new TargetInput('page', self::OTHER),
            [self::INCOMING => IncomingDecision::leadingTo(Target::leadingTo(Resource::page(self::OTHER))), self::SECOND => IncomingDecision::leadingTo(Target::leadingTo(Resource::page(self::THIRD)))],
            function () use ($mutualExclusion): void {
                $this->unpublished = $mutualExclusion->isHolding();
            },
        )($repository, self::site('/sobre-mi.html'), $mutualExclusion, new RecordingTransaction());

        self::assertTrue($this->unpublished, 'Taken off while holding.');
        self::assertSame([
            'address:/otra-vieja.html',
            'address:/sobre-mi.html',
            'address:/vieja.html',
            'page:'.self::OTHER,
            'page:'.self::THIRD,
            'page:'.self::PAGE,
        ], $mutualExclusion->lastHeld());
    }

    /**
     * @param list<Redirect> $incoming the redirects that lead to the page
     *
     * @return MockObject&RedirectRepository
     */
    private function repository(array $incoming): RedirectRepository
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturn(null);
        $repository->method('findTargeting')->willReturnCallback(static fn (Resource $resource): array => $resource->equals(Resource::page(self::PAGE)) ? $incoming : []);
        $repository->method('sourcesTargeting')->willReturn(array_map(static fn (Redirect $redirect): Source => $redirect->source(), $incoming));

        return $repository;
    }

    /**
     * @param array<string, IncomingDecision> $incoming
     */
    private function retire(RedirectRepository $repository, ?TargetInput $target, array $incoming, ?string $address = '/sobre-mi.html'): void
    {
        new ResourceRetirement(self::NEW_REDIRECT, Resource::page(self::PAGE), $target, $incoming, function (): void {
            $this->unpublished = true;
        })($repository, self::site($address), new RecordingMutualExclusion(), new RecordingTransaction());
    }

    private static function incoming(string $id, string $source): Redirect
    {
        return RedirectMother::create(id: $id, source: $source, target: Resource::page(self::PAGE), createdAt: self::CREATED);
    }

    /**
     * The site with the page at its address, which it is the last of once
     * off, and every page but OFF_SITE on it.
     */
    private static function site(?string $address): SiteResources
    {
        $site = self::createStub(SiteResources::class);
        $site->method('addressOf')->willReturn(null === $address ? null : Source::fromStored($address));
        $site->method('isOnSite')->willReturnCallback(static fn (Resource $resource): bool => !$resource->equals(Resource::page(self::OFF_SITE)));
        $site->method('isLive')->willReturn(false);
        $site->method('lastAt')->willReturn(Resource::page(self::PAGE));

        return $site;
    }
}
