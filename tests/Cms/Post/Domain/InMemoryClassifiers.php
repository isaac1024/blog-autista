<?php

declare(strict_types=1);

namespace Cms\Tests\Post\Domain;

use Cms\Post\Domain\AuthorId;
use Cms\Post\Domain\AuthorOnSite;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\SectionId;
use Cms\Post\Domain\SectionOnSite;
use Cms\Post\Domain\TagId;
use Cms\Shared\Domain\Slug;
use Cms\Tests\Author\Domain\AuthorMother;
use Cms\Tests\Taxonomy\Domain\TermMother;

/**
 * The sections, tags and authors the CMS has, as a test lays them out: by
 * default the section, the author and both tags of PostMother, the section
 * and the author on the site.
 */
final readonly class InMemoryClassifiers implements Classifiers
{
    /**
     * @param array<string, ?array{name: string, slug: string}> $sections by id, null for one off the site
     * @param array<string, ?string>                            $authors  names by id, null for one off the site
     * @param list<string>                                      $tags
     */
    public function __construct(
        private array $sections = [PostMother::SECTION => ['name' => TermMother::NAME, 'slug' => TermMother::SLUG]],
        private array $authors = [PostMother::AUTHOR => AuthorMother::NAME],
        private array $tags = [PostMother::TAG, PostMother::OTHER_TAG],
    ) {}

    #[\Override]
    public function isSection(SectionId $id): bool
    {
        return \array_key_exists($id->value(), $this->sections);
    }

    #[\Override]
    public function isAuthor(AuthorId $id): bool
    {
        return \array_key_exists($id->value(), $this->authors);
    }

    #[\Override]
    public function isTag(TagId $id): bool
    {
        return \in_array($id->value(), $this->tags, true);
    }

    #[\Override]
    public function sectionOnSite(SectionId $id): ?SectionOnSite
    {
        $section = $this->sections[$id->value()] ?? null;

        return null === $section ? null : new SectionOnSite($section['name'], new Slug($section['slug']));
    }

    #[\Override]
    public function authorOnSite(AuthorId $id): ?AuthorOnSite
    {
        $name = $this->authors[$id->value()] ?? null;

        return null === $name ? null : new AuthorOnSite($name);
    }
}
