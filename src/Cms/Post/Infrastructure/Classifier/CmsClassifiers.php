<?php

declare(strict_types=1);

namespace Cms\Post\Infrastructure\Classifier;

use Cms\Author\Application\AuthorExistenceChecker;
use Cms\Author\Application\AuthorOnSiteFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Post\Domain\AuthorId;
use Cms\Post\Domain\AuthorOnSite;
use Cms\Post\Domain\Classifiers;
use Cms\Post\Domain\SectionId;
use Cms\Post\Domain\SectionOnSite;
use Cms\Post\Domain\TagId;
use Cms\Shared\Domain\Slug;
use Cms\Taxonomy\Application\TermExistenceChecker;
use Cms\Taxonomy\Application\TermOnSiteFinder;
use Cms\Taxonomy\Domain\Taxonomy;
use Cms\Taxonomy\Domain\TermRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the taxonomies and the authors of the CMS for
 * the sections, tags and authors of posts, as they are saved.
 */
#[AsAlias(Classifiers::class)]
final readonly class CmsClassifiers implements Classifiers
{
    public function __construct(
        private TermRepository $termRepository,
        private AuthorRepository $authorRepository,
    ) {}

    #[\Override]
    public function isSection(SectionId $id): bool
    {
        return new TermExistenceChecker(Taxonomy::Section, $id->value())($this->termRepository);
    }

    #[\Override]
    public function isAuthor(AuthorId $id): bool
    {
        return new AuthorExistenceChecker($id->value())($this->authorRepository);
    }

    #[\Override]
    public function isTag(TagId $id): bool
    {
        return new TermExistenceChecker(Taxonomy::Tag, $id->value())($this->termRepository);
    }

    #[\Override]
    public function sectionOnSite(SectionId $id): ?SectionOnSite
    {
        $section = new TermOnSiteFinder(Taxonomy::Section, $id->value())($this->termRepository);

        return null === $section ? null : new SectionOnSite($section->name, new Slug($section->slug));
    }

    #[\Override]
    public function authorOnSite(AuthorId $id): ?AuthorOnSite
    {
        $author = new AuthorOnSiteFinder($id->value())($this->authorRepository);

        return null === $author ? null : new AuthorOnSite($author->name);
    }
}
