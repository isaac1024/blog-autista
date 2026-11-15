<?php

declare(strict_types=1);

namespace Cms\External\Domain;

/**
 * Where a study is recorded, which says what its id is and who tells what
 * it is.
 */
enum StudyRegistry: string
{
    case Doi = 'doi';
    case PubMed = 'pubmed';
    case Arxiv = 'arxiv';
}
