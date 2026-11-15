<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\SettingsContentsRelator;
use Cms\Relation\Domain\CmsSettingsContentsLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use PHPUnit\Framework\TestCase;

final class SettingsContentsRelatorTest extends TestCase
{
    private const string SOURCE = '01a1da50-0000-7000-8000-00000000c001';

    /**
     * Each kind of content its menus links to, apart, with none left of those
     * it no longer links to.
     */
    public function testItRelatesTheSettingsToTheContentsMenusLinksToNow(): void
    {
        $replaced = [];
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::exactly(4))->method('replace')->willReturnCallback(static function (ResourceType $source, string $id, ResourceType $target, array $ids) use (&$replaced): void {
            $replaced[] = [$source, $id, $target, $ids];
        });

        new SettingsContentsRelator(new CmsSettingsContentsLinked(self::SOURCE, ['post'], ['term'], [], ['external']))($repository);

        self::assertSame([
            [ResourceType::Settings, self::SOURCE, ResourceType::Post, ['post']],
            [ResourceType::Settings, self::SOURCE, ResourceType::Term, ['term']],
            [ResourceType::Settings, self::SOURCE, ResourceType::Author, []],
            [ResourceType::Settings, self::SOURCE, ResourceType::External, ['external']],
        ], $replaced);
    }
}
