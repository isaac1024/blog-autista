<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\PreviousAddressDecision;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Application\ResourcePublication;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;
use Shared\Tests\Domain\ClockMother;
use Shared\Tests\Domain\DomainErrorAssertions;
use Shared\Tests\Domain\RecordingMutualExclusion;
use Shared\Tests\Domain\RecordingTransaction;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Publishing a page keeps the redirects true to it, and goes ahead only on
 * what the editor confirmed: the redirect at the address it takes goes, no
 * redirect knows it as its origin any longer, and the address it leaves
 * does what the editor says. The page RedirectMother::PAGE is on the site.
 */
final class ResourcePublicationTest extends TestCase
{
    use ClockSensitiveTrait;
    use DomainErrorAssertions;

    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000d1';
    private const string NEW_REDIRECT = '0196f3a0-0000-7000-8000-0000000000f1';
    private const string CREATED = '2026-10-01 09:00:00';

    private bool $published = false;

    #[\Override]
    protected function setUp(): void
    {
        self::mockTime(ClockMother::now());
    }

    public function testItPublishesAtAnAddressWithARedirectOnceConfirmedAndDeletesIt(): void
    {
        $replaced = RedirectMother::create(source: '/nueva.html');
        $repository = $this->repository(['/nueva.html' => $replaced]);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($replaced));
        $outlook = self::fingerprint($repository, null, '/nueva.html');

        $this->publish($repository, null, '/nueva.html', PreviousAddressDecision::undecided(), $outlook);

        self::assertTrue($this->published);
    }

    /**
     * A confirmation of nothing shown, or of what changed since it was
     * shown, is no confirmation.
     */
    public function testItRefusesToPublishWhatTheEditorWasNotShown(): void
    {
        $repository = $this->repository(['/nueva.html' => RedirectMother::create(source: '/nueva.html')]);
        $repository->expects(self::never())->method('delete');
        $changed = self::createStub(RedirectRepository::class);
        $changed->method('findBySource')->willReturn(RedirectMother::gone(source: '/nueva.html'));
        $stale = self::fingerprint($changed, null, '/nueva.html');

        self::assertTells('errors.redirect.outlook_changed', [], fn () => $this->publish($repository, null, '/nueva.html', PreviousAddressDecision::undecided(), ''));
        self::assertTells('errors.redirect.outlook_changed', [], fn () => $this->publish($repository, null, '/nueva.html', PreviousAddressDecision::undecided(), $stale));
        self::assertFalse($this->published);
    }

    /**
     * A resource whose address never changes publishes whatever it does.
     */
    public function testItPublishesWithoutConfirmationWhenToldSo(): void
    {
        $replaced = RedirectMother::create(source: '/nueva.html');
        $repository = $this->repository(['/nueva.html' => $replaced]);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($replaced));

        $this->publish($repository, null, '/nueva.html', PreviousAddressDecision::keep(), null);

        self::assertTrue($this->published);
    }

    /**
     * Back at its address, the page deletes the redirect it left there
     * without asking.
     */
    public function testItDeletesItsOwnFormerRedirectWithoutAsking(): void
    {
        $own = RedirectMother::create(source: '/sobre-mi.html', origin: Resource::page(self::PAGE));
        $repository = $this->repository(['/sobre-mi.html' => $own]);
        $repository->expects(self::once())->method('delete')->with(self::identicalTo($own));

        $this->publish($repository, '/sobre-mi.html', '/sobre-mi.html', PreviousAddressDecision::undecided(), '');

        self::assertTrue($this->published);
    }

    public function testItRefusesToMoveThePageWithoutSayingWhatItsFormerAddressDoes(): void
    {
        $repository = $this->repository([]);
        $repository->expects(self::never())->method('save');
        $outlook = self::fingerprint($repository, '/sobre-mi.html', '/quien-soy.html');

        self::assertTells('errors.redirect.undecided_previous', [], fn () => $this->publish($repository, '/sobre-mi.html', '/quien-soy.html', PreviousAddressDecision::undecided(), $outlook));
        self::assertFalse($this->published);
    }

    public function testItLeadsTheAddressThePageLeavesWhereTheEditorSays(): void
    {
        $repository = $this->repository([]);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::create(id: self::NEW_REDIRECT, source: '/sobre-mi.html', target: Resource::page(self::PAGE))));
        $outlook = self::fingerprint($repository, '/sobre-mi.html', '/quien-soy.html');

        $this->publish($repository, '/sobre-mi.html', '/quien-soy.html', PreviousAddressDecision::leadTo(new TargetInput('page', self::PAGE)), $outlook);

        self::assertTrue($this->published);
    }

    /**
     * The address it leaves keeps its redirect, led where the editor says.
     */
    public function testItLeadsTheRedirectOfTheAddressThePageLeavesElsewhere(): void
    {
        $repository = $this->repository(['/sobre-mi.html' => RedirectMother::gone(source: '/sobre-mi.html', createdAt: self::CREATED)]);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::create(source: '/sobre-mi.html', target: Resource::page(RedirectMother::PAGE), createdAt: self::CREATED, updatedAt: ClockMother::NOW)));
        $outlook = self::fingerprint($repository, '/sobre-mi.html', '/quien-soy.html');

        $this->publish($repository, '/sobre-mi.html', '/quien-soy.html', PreviousAddressDecision::leadTo(new TargetInput('page', RedirectMother::PAGE)), $outlook);
    }

    public function testItLeavesTheAddressThePageLeavesAsItIsWhenTold(): void
    {
        $repository = $this->repository([]);
        $repository->expects(self::never())->method('save');
        $outlook = self::fingerprint($repository, '/sobre-mi.html', '/quien-soy.html');

        $this->publish($repository, '/sobre-mi.html', '/quien-soy.html', PreviousAddressDecision::keep(), $outlook);

        self::assertTrue($this->published);
    }

    /**
     * Back on the site, the page is no longer the one that was at its former
     * address: that redirect stays, as an address only.
     */
    public function testItForgetsBeingTheOriginOfAnyRedirectOnceBackOnTheSite(): void
    {
        $repository = $this->repository([]);
        $repository->expects(self::once())
            ->method('findByOrigin')
            ->with(self::equalTo(Resource::page(self::PAGE)))
            ->willReturn([RedirectMother::create(source: '/sobre-mi.html', origin: Resource::page(self::PAGE), createdAt: self::CREATED)]);
        $repository->expects(self::once())
            ->method('save')
            ->with(self::equalTo(RedirectMother::create(source: '/sobre-mi.html', createdAt: self::CREATED, updatedAt: ClockMother::NOW)));

        $this->publish($repository, null, '/quien-soy.html', PreviousAddressDecision::undecided(), '');
    }

    /**
     * It holds the page, the address it takes, the one it leaves and where
     * that one will lead, and does it all within one transaction.
     */
    public function testItHoldsThePageBothAddressesAndTheTargetWhilePublishingAtOnce(): void
    {
        $mutualExclusion = new RecordingMutualExclusion();
        $transaction = new RecordingTransaction();
        $repository = $this->repository([]);
        $repository->expects(self::once())->method('save');
        $outlook = self::fingerprint($repository, '/sobre-mi.html', '/quien-soy.html');

        new ResourcePublication(
            self::NEW_REDIRECT,
            Resource::page(self::PAGE),
            PreviousAddressDecision::leadTo(new TargetInput('page', RedirectMother::PAGE)),
            $outlook,
            function () use ($mutualExclusion, $transaction): void {
                $this->published = $mutualExclusion->isHolding() && $transaction->isRunning();
            },
        )($repository, self::site('/sobre-mi.html', '/quien-soy.html'), $mutualExclusion, $transaction);

        self::assertTrue($this->published, 'Published while holding, within the transaction.');
        self::assertSame(['address:/quien-soy.html', 'address:/sobre-mi.html', 'page:'.RedirectMother::PAGE, 'page:'.self::PAGE], $mutualExclusion->lastHeld());
    }

    /**
     * @param array<string, Redirect> $redirects by their address
     *
     * @return \PHPUnit\Framework\MockObject\MockObject&RedirectRepository
     */
    private function repository(array $redirects): RedirectRepository
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->method('findBySource')->willReturnCallback(static fn (Source $source): ?Redirect => $redirects[$source->value()] ?? null);

        return $repository;
    }

    private function publish(RedirectRepository $repository, ?string $current, ?string $next, PreviousAddressDecision $previous, ?string $outlook): void
    {
        new ResourcePublication(self::NEW_REDIRECT, Resource::page(self::PAGE), $previous, $outlook, function (): void {
            $this->published = true;
        })($repository, self::site($current, $next), new RecordingMutualExclusion(), new RecordingTransaction());
    }

    /**
     * The fingerprint of what the confirmation shows the editor.
     */
    private static function fingerprint(RedirectRepository $repository, ?string $current, ?string $next): string
    {
        return new PublicationOutlookFinder(Resource::page(self::PAGE))($repository, self::site($current, $next))->fingerprint;
    }

    /**
     * The site with the page at the address it has, or last had, and the one
     * it takes once published; every page on it, and no address of its own
     * there.
     */
    private static function site(?string $current, ?string $next): SiteResources
    {
        $site = self::createStub(SiteResources::class);
        $site->method('addressOf')->willReturn(null === $current ? null : Source::fromStored($current));
        $site->method('nextAddressOf')->willReturn(null === $next ? null : Source::fromStored($next));
        $site->method('isOnSite')->willReturn(true);
        $site->method('isLive')->willReturn(false);
        $site->method('lastAt')->willReturn(null);

        return $site;
    }
}
