<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\IncomingRedirectsFinder;
use Cms\Redirect\Application\RedirectsPage;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Cms\Redirect\Domain\TargetFilter;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Criteria\Criteria;
use Shared\Domain\Criteria\Order;
use Shared\Domain\Criteria\OrderDirection;

/**
 * The redirects that lead to a resource, newest first, a page at a time.
 */
final class IncomingRedirectsFinderTest extends TestCase
{
    public function testItListsOnlyTheRedirectsThatLeadToTheResource(): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('matching')
            ->with(self::equalTo(new Criteria(
                filters: [TargetFilter::type(ResourceType::Page), TargetFilter::id(RedirectMother::PAGE)],
                order: new Order('id', OrderDirection::Descending),
                limit: RedirectsPage::PAGE_SIZE + 1,
                offset: RedirectsPage::PAGE_SIZE,
            )))
            ->willReturn([RedirectMother::create(source: '/hacia-ella.html')]);

        $incoming = new IncomingRedirectsFinder(ResourceType::Page, RedirectMother::PAGE, 2)($repository);

        self::assertSame([['/hacia-ella.html'], false], [array_map(static fn ($redirect): string => $redirect->source, $incoming->redirects), $incoming->hasMore]);
    }
}
