<?php

declare(strict_types=1);

namespace Cms\Tests\Taxonomy\Infrastructure\Controller;

use Cms\Taxonomy\Domain\Term;
use Cms\Taxonomy\Domain\TermRepository;
use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Keeps and reads back the terms the controllers of the taxonomies act on.
 */
abstract class TermControllerTestCase extends CmsWebTestCase
{
    protected const string HOST = 'http://cms.blogautista.localhost';

    protected function saveTerm(Term $term): Term
    {
        self::getContainer()->get(TermRepository::class)->save($term);

        return $term;
    }

    /**
     * Reads the term back from the database, not from Doctrine's identity map.
     */
    protected function storedTerm(Term $term): ?Term
    {
        self::getContainer()->get(EntityManagerInterface::class)->clear();

        return self::getContainer()->get(TermRepository::class)->find($term->id());
    }
}
