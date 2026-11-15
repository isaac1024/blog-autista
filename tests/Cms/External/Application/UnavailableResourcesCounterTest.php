<?php

declare(strict_types=1);

namespace Cms\Tests\External\Application;

use Cms\External\Application\UnavailableResourcesCounter;
use Cms\External\Domain\ExternalResourceRepository;
use PHPUnit\Framework\TestCase;

final class UnavailableResourcesCounterTest extends TestCase
{
    public function testItCountsTheResourcesWhoseAddressIsGone(): void
    {
        $repository = $this->createMock(ExternalResourceRepository::class);
        $repository->expects(self::once())->method('countUnavailable')->willReturn(3);

        self::assertSame(3, new UnavailableResourcesCounter()($repository));
    }
}
