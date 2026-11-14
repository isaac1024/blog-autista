<?php

declare(strict_types=1);

namespace Web\Taxonomy\Application;

use Web\Taxonomy\Domain\CmsTermPublished;
use Web\Taxonomy\Domain\Term;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Puts on the site the version of a term the CMS published, in place of the
 * one there, if any.
 */
final readonly class TermSaver
{
    public function __construct(private CmsTermPublished $event) {}

    public function __invoke(TermRepository $termRepository): void
    {
        $term = $termRepository->find(new TermId($this->event->aggregateId()));

        if (null === $term) {
            $term = Term::publish($this->event);
        } else {
            $term->republish($this->event);
        }

        $term->save($termRepository);
    }
}
