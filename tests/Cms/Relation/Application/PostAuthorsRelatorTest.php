<?php

declare(strict_types=1);

namespace Cms\Tests\Relation\Application;

use Cms\Relation\Application\PostAuthorsRelator;
use Cms\Relation\Domain\CmsPostAuthorsUsed;
use Cms\Relation\Domain\RelationRepository;
use Cms\Relation\Domain\ResourceType;
use PHPUnit\Framework\TestCase;

final class PostAuthorsRelatorTest extends TestCase
{
    private const string SOURCE = '01a1da50-0000-7000-8000-00000000c001';

    private const string TARGET = '01a1da50-0000-7000-8000-00000000d001';

    private const string OTHER_TARGET = '01a1da50-0000-7000-8000-00000000d002';

    public function testItRelatesThePostToTheAuthorsItIsSignedByNow(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Post, self::SOURCE, ResourceType::Author, [self::TARGET, self::OTHER_TARGET]);

        new PostAuthorsRelator(new CmsPostAuthorsUsed(self::SOURCE, [self::TARGET, self::OTHER_TARGET]))($repository);
    }

    public function testItRelatesThePostToNoAuthorWhenItHasNone(): void
    {
        $repository = $this->createMock(RelationRepository::class);
        $repository->expects(self::once())->method('replace')->with(ResourceType::Post, self::SOURCE, ResourceType::Author, []);

        new PostAuthorsRelator(new CmsPostAuthorsUsed(self::SOURCE, []))($repository);
    }
}
