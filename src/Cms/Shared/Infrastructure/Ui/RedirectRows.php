<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Ui;

use Cms\Redirect\Application\RedirectResponse;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Rows of a list of redirects: each by its address, where it leads and what
 * it answers, opening where it is edited.
 */
final readonly class RedirectRows
{
    public function __construct(
        private RedirectNames $redirectNames,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param list<RedirectResponse> $redirects
     *
     * @return list<PageComponent>
     */
    public function __invoke(array $redirects): array
    {
        $names = ($this->redirectNames)($redirects);

        return array_map(fn (RedirectResponse $redirect): PageComponent => new PageComponent('Shared:ResourceRow', [
            'title' => $redirect->source,
            'url' => $this->urlGenerator->generate('cms_redirects_edit', ['id' => $redirect->id]),
            'meta' => $names[$redirect->id]['target'],
            'status' => $this->translator->trans(null === $redirect->targetType ? 'cms.redirects.status.gone' : 'cms.redirects.status.moved'),
        ]), $redirects);
    }
}
