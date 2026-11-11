<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\PublicationOutlook;
use Cms\Redirect\Application\PublicationOutlookFinder;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\SiteResources;
use Cms\Redirect\Domain\Source;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What publishing a page as it is saved would do to the redirects: the one
 * at the address it takes goes, and the address it leaves needs a decision.
 */
final class PublicationOutlookFinderTest extends TestCase
{
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000d1';
    private const string OTHER_PAGE = '0196f3a0-0000-7000-8000-0000000000d2';
    private const string OTHER_REDIRECT = '0196f3a0-0000-7000-8000-0000000000e2';
    private const string THIRD_REDIRECT = '0196f3a0-0000-7000-8000-0000000000e3';

    public function testItAsksNothingWhenPublishingChangesNoRedirect(): void
    {
        $outlook = self::outlook(current: '/sobre-mi.html', next: '/sobre-mi.html', redirects: []);

        self::assertEquals(new PublicationOutlook('/sobre-mi.html', null, null, null, false, ''), $outlook);
    }

    public function testItTellsOfTheRedirectAtTheAddressItTakes(): void
    {
        $replaced = RedirectMother::create(source: '/nueva.html');

        $outlook = self::outlook(current: null, next: '/nueva.html', redirects: ['/nueva.html' => $replaced]);

        self::assertEquals(RedirectResponse::fromRedirect($replaced), $outlook->replaced);
        self::assertSame([null, true], [$outlook->previous, $outlook->asksFirst]);
        self::assertNotSame('', $outlook->fingerprint);
    }

    /**
     * The redirect of its own former address goes without a word: it is
     * back where it was.
     */
    public function testItTellsNothingOfTheRedirectOfItsOwnFormerAddress(): void
    {
        $own = RedirectMother::create(source: '/sobre-mi.html', origin: Resource::page(self::PAGE));

        $outlook = self::outlook(current: '/sobre-mi.html', next: '/sobre-mi.html', redirects: ['/sobre-mi.html' => $own]);

        self::assertEquals(new PublicationOutlook('/sobre-mi.html', null, null, null, false, ''), $outlook);
    }

    public function testItAsksAboutTheAddressItLeavesWithItsRedirectIfAny(): void
    {
        $previous = RedirectMother::gone(source: '/sobre-mi.html');

        $outlook = self::outlook(current: '/sobre-mi.html', next: '/quien-soy.html', redirects: ['/sobre-mi.html' => $previous]);

        self::assertSame(['/quien-soy.html', '/sobre-mi.html', true], [$outlook->next, $outlook->previous, $outlook->asksFirst]);
        self::assertEquals(RedirectResponse::fromRedirect($previous), $outlook->previousRedirect);
    }

    /**
     * The fingerprint changes once what it tells of does, so a confirmation
     * of what was shown before is known to be stale.
     */
    public function testItsFingerprintChangesWithTheRedirectItTellsOf(): void
    {
        $before = self::outlook(current: null, next: '/nueva.html', redirects: ['/nueva.html' => RedirectMother::create(source: '/nueva.html')]);
        $after = self::outlook(current: null, next: '/nueva.html', redirects: ['/nueva.html' => RedirectMother::gone(source: '/nueva.html')]);
        $again = self::outlook(current: null, next: '/nueva.html', redirects: ['/nueva.html' => RedirectMother::create(source: '/nueva.html')]);

        self::assertNotSame($before->fingerprint, $after->fingerprint);
        self::assertSame($before->fingerprint, $again->fingerprint);
    }

    /**
     * Whatever of it changes, the fingerprint does: either address, the
     * redirect at the address it takes, and the one at the address it
     * leaves, by its id, where it leads and its origin.
     *
     * @param array<string, Redirect> $redirects
     */
    #[DataProvider('changes')]
    public function testItsFingerprintTellsEveryChangeApart(?string $current, string $next, array $redirects): void
    {
        $shown = self::outlook(current: '/sobre-mi.html', next: '/quien-soy.html', redirects: [
            '/quien-soy.html' => RedirectMother::create(source: '/quien-soy.html'),
            '/sobre-mi.html' => RedirectMother::create(id: self::OTHER_REDIRECT, source: '/sobre-mi.html'),
        ]);

        self::assertNotSame($shown->fingerprint, self::outlook($current, $next, $redirects)->fingerprint);
    }

    /**
     * @return iterable<string, array{?string, string, array<string, Redirect>}>
     */
    public static function changes(): iterable
    {
        $taken = RedirectMother::create(source: '/quien-soy.html');
        $left = RedirectMother::create(id: self::OTHER_REDIRECT, source: '/sobre-mi.html');

        yield 'the address it takes' => ['/sobre-mi.html', '/soy-yo.html', ['/soy-yo.html' => $taken, '/sobre-mi.html' => $left]];
        yield 'the address it leaves' => ['/acerca.html', '/quien-soy.html', ['/quien-soy.html' => $taken, '/acerca.html' => $left]];
        yield 'the redirect it deletes, by its id' => ['/sobre-mi.html', '/quien-soy.html', ['/quien-soy.html' => RedirectMother::create(id: self::THIRD_REDIRECT, source: '/quien-soy.html'), '/sobre-mi.html' => $left]];
        yield 'where the redirect it deletes leads' => ['/sobre-mi.html', '/quien-soy.html', ['/quien-soy.html' => RedirectMother::create(source: '/quien-soy.html', target: Resource::page(self::OTHER_PAGE)), '/sobre-mi.html' => $left]];
        yield 'the origin of the redirect it deletes' => ['/sobre-mi.html', '/quien-soy.html', ['/quien-soy.html' => RedirectMother::create(source: '/quien-soy.html', origin: Resource::page(self::OTHER_PAGE)), '/sobre-mi.html' => $left]];
        yield 'the redirect of the address it leaves' => ['/sobre-mi.html', '/quien-soy.html', ['/quien-soy.html' => $taken, '/sobre-mi.html' => RedirectMother::gone(id: self::OTHER_REDIRECT, source: '/sobre-mi.html')]];
        yield 'no redirect at the address it leaves' => ['/sobre-mi.html', '/quien-soy.html', ['/quien-soy.html' => $taken]];
    }

    /**
     * @param array<string, Redirect> $redirects by their address
     */
    private static function outlook(?string $current, ?string $next, array $redirects): PublicationOutlook
    {
        $site = self::createStub(SiteResources::class);
        $site->method('addressOf')->willReturn(null === $current ? null : Source::fromStored($current));
        $site->method('nextAddressOf')->willReturn(null === $next ? null : Source::fromStored($next));
        $repository = self::createStub(RedirectRepository::class);
        $repository->method('findBySource')->willReturnCallback(static fn (Source $source): ?Redirect => $redirects[$source->value()] ?? null);

        return new PublicationOutlookFinder(Resource::page(self::PAGE))($repository, $site);
    }
}
