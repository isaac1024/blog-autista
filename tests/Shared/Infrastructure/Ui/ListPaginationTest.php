<?php

declare(strict_types=1);

namespace Shared\Tests\Infrastructure\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use Shared\Infrastructure\Ui\ListPagination;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ListPaginationTest extends KernelTestCase
{
    /**
     * Links are generated as in a request to the CMS, which serves them
     * under its own host.
     */
    #[\Override]
    protected function setUp(): void
    {
        self::getContainer()->get(UrlGeneratorInterface::class)->getContext()->setHost('cms.blogautista.localhost');
    }

    /**
     * The page before only past the first, the one after only while more
     * follow; the first goes without a number, and every link keeps the
     * parameters of the list.
     *
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('pages')]
    public function testItLeadsToThePagesBeforeAndAfter(array $parameters, int $page, bool $hasMore, ?string $previous, ?string $next): void
    {
        $pagination = self::getContainer()->get(ListPagination::class)('cms_pages', $parameters, $page, $hasMore);

        self::assertSame($previous, $pagination['previousUrl']);
        self::assertSame($next, $pagination['nextUrl']);
    }

    /**
     * @return iterable<string, array{array<string, int|string>, int, bool, ?string, ?string}>
     */
    public static function pages(): iterable
    {
        yield 'a single page' => [[], 1, false, null, null];
        yield 'the first of several' => [[], 1, true, null, '/pages?page=2'];
        yield 'the second leads back to the first, without a number' => [[], 2, true, '/pages', '/pages?page=3'];
        yield 'the last' => [[], 3, false, '/pages?page=2', null];
        yield 'a search keeps its text' => [['title' => 'guía'], 2, true, '/pages?title=gu%C3%ADa', '/pages?title=gu%C3%ADa&page=3'];
    }
}
