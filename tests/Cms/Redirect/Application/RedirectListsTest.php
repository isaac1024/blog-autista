<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\IncomingRedirectsFinder;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\RedirectResponse;
use Cms\Redirect\Application\RedirectSearcher;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\SiteResources;
use Cms\Tests\Page\Domain\PageMother;
use Shared\Domain\Event\EventBus;
use Shared\Domain\MutualExclusion;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The redirects are listed newest first, twenty a page, telling whether
 * more follow: all of them, those whose address has a text, and those that
 * lead to a resource.
 */
final class RedirectListsTest extends KernelTestCase
{
    private const string HOST = 'www.blogautista.localhost';
    private const string PAGE = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string OTHER = '0196f3a0-0000-7000-8000-0000000000a2';

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: self::PAGE, title: 'Una', slug: 'una'));
        $pages->save(PageMother::published(id: self::OTHER, title: 'Otra', slug: 'otra'));
    }

    public function testItListsTwentyANewestFirstAndTellsMoreFollow(): void
    {
        $this->redirects(21);

        $first = new RedirectSearcher('', 1)($this->repository());
        $second = new RedirectSearcher('', 2)($this->repository());

        self::assertSame([20, true, '/vieja-21.html', '/vieja-2.html'], [\count($first->redirects), $first->hasMore, $first->redirects[0]->source, $first->redirects[19]->source]);
        self::assertSame([['/vieja-1.html'], false], [$this->sources($second->redirects), $second->hasMore]);
    }

    public function testItTellsNoMoreFollowAFullPage(): void
    {
        $this->redirects(20);

        $page = new RedirectSearcher('', 1)($this->repository());

        self::assertSame([20, false], [\count($page->redirects), $page->hasMore]);
    }

    public function testItFindsTheRedirectsWhoseAddressHasATextWrittenAnyWay(): void
    {
        $this->redirects(2);
        $this->create('/Guía-Antigua.html', null);

        self::assertSame(['/guía-antigua.html'], $this->sources(new RedirectSearcher('  GUÍA ', 1)($this->repository())->redirects));
    }

    public function testItListsOnlyTheRedirectsThatLeadToAResource(): void
    {
        $this->redirects(2);
        $this->create('/hacia-otra.html', self::OTHER);

        $incoming = new IncomingRedirectsFinder(ResourceType::Page, self::OTHER, 1)($this->repository());

        self::assertSame([['/hacia-otra.html'], false], [$this->sources($incoming->redirects), $incoming->hasMore]);
    }

    /**
     * Redirects from /vieja-1.html on, one after the other, to the first page.
     */
    private function redirects(int $count): void
    {
        for ($number = 1; $number <= $count; ++$number) {
            $this->create(\sprintf('/vieja-%d.html', $number), self::PAGE);
        }
    }

    private function create(string $path, ?string $target): void
    {
        // Ids are time-ordered, so each is newer than the one before.
        new RedirectCreator(RedirectId::random()->value(), 'https://'.self::HOST.$path, new TargetInput(null === $target ? null : 'page', $target), self::HOST)(
            $this->repository(),
            self::getContainer()->get(EventBus::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        );
    }

    /**
     * @param list<RedirectResponse> $redirects
     *
     * @return list<string>
     */
    private function sources(array $redirects): array
    {
        return array_map(static fn (RedirectResponse $redirect): string => $redirect->source, $redirects);
    }

    private function repository(): RedirectRepository
    {
        return self::getContainer()->get(RedirectRepository::class);
    }
}
