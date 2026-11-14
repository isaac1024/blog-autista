<?php

declare(strict_types=1);

namespace Cms\Taxonomy\Infrastructure\Redirect;

use Cms\Redirect\Application\TargetChecker;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Cms\Taxonomy\Domain\TermId;
use Cms\Taxonomy\Domain\TermRedirects;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Outbound adapter that asks the redirects of the CMS whether any leads to
 * a term. It reads them as they are saved, not through events, so a redirect
 * just created already keeps its term on the site.
 */
#[AsAlias(TermRedirects::class)]
final readonly class CmsTermRedirects implements TermRedirects
{
    public function __construct(private RedirectRepository $redirectRepository) {}

    #[\Override]
    public function isTargeted(TermId $id): bool
    {
        return new TargetChecker(ResourceType::Term, $id->value())($this->redirectRepository);
    }
}
