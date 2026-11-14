<?php

declare(strict_types=1);

namespace Web\Taxonomy\Application;

use Web\Taxonomy\Domain\CmsTermUnpublished;
use Web\Taxonomy\Domain\TermId;
use Web\Taxonomy\Domain\TermRepository;

/**
 * Takes off the site a term the CMS unpublished. A term already gone stays
 * gone, so the same event can be taken twice.
 */
final readonly class TermRemover
{
    public function __construct(private CmsTermUnpublished $event) {}

    public function __invoke(TermRepository $termRepository): void
    {
        $termRepository->find(new TermId($this->event->aggregateId()))?->unpublish($termRepository);
    }
}
