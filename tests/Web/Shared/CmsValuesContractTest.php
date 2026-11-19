<?php

declare(strict_types=1);

namespace Web\Tests\Shared;

use Cms\Page\Domain\PageType as CmsPageType;
use Cms\Shared\Domain\Robots as CmsRobots;
use Cms\Taxonomy\Domain\Taxonomy as CmsTaxonomy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Web\Page\Domain\PageType;
use Web\Shared\Domain\Robots;
use Web\Taxonomy\Domain\Taxonomy;

/**
 * The site reads in the events of the CMS values the CMS writes from enums
 * of its own: a value added on one side alone would leave an event the site
 * can't read in the queue of failures. Each enum of the site knows the
 * values of its twin in the CMS, and no other.
 */
final class CmsValuesContractTest extends TestCase
{
    /**
     * @param class-string<\BackedEnum> $cms
     * @param class-string<\BackedEnum> $site
     */
    #[DataProvider('twins')]
    public function testTheSiteKnowsTheValuesTheCmsWrites(string $cms, string $site): void
    {
        self::assertEqualsCanonicalizing(
            array_map(static fn (\BackedEnum $case): int|string => $case->value, $cms::cases()),
            array_map(static fn (\BackedEnum $case): int|string => $case->value, $site::cases()),
        );
    }

    /**
     * @return iterable<string, array{class-string<\BackedEnum>, class-string<\BackedEnum>}>
     */
    public static function twins(): iterable
    {
        yield 'what search engines may do' => [CmsRobots::class, Robots::class];
        yield 'the kinds of page' => [CmsPageType::class, PageType::class];
        yield 'the taxonomies' => [CmsTaxonomy::class, Taxonomy::class];
    }
}
