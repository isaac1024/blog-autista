<?php

declare(strict_types=1);

namespace Cms\Relation\Infrastructure\Controller;

use Cms\Author\Application\AuthorResponse;
use Cms\Author\Application\AuthorsFinder;
use Cms\Author\Domain\AuthorRepository;
use Cms\Shared\Infrastructure\Ui\AuthorState;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The authors whose bio uses a resource, each as a row that opens its editor.
 */
final readonly class AuthorUserRows
{
    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        private AuthorRepository $authorRepository,
        private AuthorState $authorState,
    ) {}

    /**
     * @param list<string> $ids
     *
     * @return list<PageComponent>
     */
    public function __invoke(array $ids): array
    {
        return array_map($this->row(...), new AuthorsFinder($ids)($this->authorRepository));
    }

    private function row(AuthorResponse $author): PageComponent
    {
        return new PageComponent('Shared:ResourceRow', [
            'title' => $author->name,
            'url' => $this->urlGenerator->generate('cms_authors_edit', ['id' => $author->id]),
            'meta' => \sprintf('%s · %s', $this->translator->trans('cms.relations.types.author'), $this->authorState->lastUpdate($author)),
            'status' => $this->authorState->label($author),
        ]);
    }
}
