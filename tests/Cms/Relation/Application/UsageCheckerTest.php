<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\UsageChecker;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use Cms\Tests\Relation\Domain\CmsPageImagesUsedMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UsageCheckerTest extends TestCase
{
    #[DataProvider('usages')]
    public function testItTellsWhetherAnythingUsesTheResource(bool $used): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('isUsed')->with(ResourceType::Image, CmsPageImagesUsedMother::IMAGE)->willReturn($used);

        self::assertSame($used, (new UsageChecker(ResourceType::Image, CmsPageImagesUsedMother::IMAGE))($repository));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function usages(): iterable
    {
        yield 'in use' => [true];
        yield 'not in use' => [false];
    }
}
