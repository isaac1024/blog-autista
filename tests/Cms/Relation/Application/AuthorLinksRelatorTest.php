<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\AuthorLinksRelator;
use Cms\Relation\Domain\CmsAuthorPagesLinked;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use PHPUnit\Framework\TestCase;

final class AuthorLinksRelatorTest extends TestCase
{
    private const string SOURCE = '01a1da50-0000-7000-8000-00000000c001';

    private const string TARGET = '01a1da50-0000-7000-8000-00000000d001';

    private const string OTHER_TARGET = '01a1da50-0000-7000-8000-00000000d002';

    public function testItRelatesTheAuthorToThePagesItsBioLinksToNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Author, self::SOURCE, ResourceType::Page, [self::TARGET, self::OTHER_TARGET]);

        new AuthorLinksRelator(new CmsAuthorPagesLinked(self::SOURCE, [self::TARGET, self::OTHER_TARGET]))($repository);
    }

    public function testItRelatesTheAuthorToNoPageWhenItHasNone(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Author, self::SOURCE, ResourceType::Page, []);

        new AuthorLinksRelator(new CmsAuthorPagesLinked(self::SOURCE, []))($repository);
    }
}
