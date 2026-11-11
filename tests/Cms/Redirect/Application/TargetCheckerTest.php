<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Redirect\Application\TargetChecker;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Domain\ResourceType;
use Cms\Tests\Redirect\Domain\RedirectMother;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class TargetCheckerTest extends TestCase
{
    #[TestWith([true])]
    #[TestWith([false])]
    public function testItTellsWhetherAnyRedirectLeadsToTheResource(bool $targeted): void
    {
        $repository = $this->createMock(RedirectRepository::class);
        $repository->expects(self::once())
            ->method('isTargeted')
            ->with(self::equalTo(Resource::image(RedirectMother::IMAGE)))
            ->willReturn($targeted);

        self::assertSame($targeted, new TargetChecker(ResourceType::Image, RedirectMother::IMAGE)($repository));
    }
}
