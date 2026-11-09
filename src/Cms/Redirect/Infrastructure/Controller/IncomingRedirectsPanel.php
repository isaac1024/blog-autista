<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\IncomingRedirectsFinder;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\ResourceType;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The redirects that lead to a resource, for the page that tells where it is
 * used: while there is any, it stays on the site. The first of them, newest
 * first; the list of redirects has them all.
 */
final readonly class IncomingRedirectsPanel
{
    public function __construct(
        private RedirectRepository $redirectRepository,
        private RedirectRows $redirectRows,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ResourceType $type, string $id, string $name): PageComponent
    {
        $redirects = new IncomingRedirectsFinder($type, $id, 1)($this->redirectRepository);
        $texts = \sprintf('cms.redirects.incoming.%s', $type->value);

        if ([] === $redirects->redirects) {
            return new PageComponent('Shared:Panel', [
                'title' => $this->translator->trans('cms.redirects.incoming.title'),
                'description' => $this->translator->trans($texts.'.empty', ['%name%' => $name]),
            ]);
        }

        $children = [
            new PageComponent('Shared:ResourceList', [
                'label' => $this->translator->trans('cms.redirects.incoming.title'),
            ], ($this->redirectRows)($redirects->redirects)),
        ];

        if ($redirects->hasMore) {
            $children[] = new PageComponent('Shared:ButtonLink', [
                'url' => $this->urlGenerator->generate('cms_redirects', ['target' => \sprintf('%s:%s', $type->value, $id)]),
                'label' => $this->translator->trans('cms.redirects.incoming.all'),
            ]);
        }

        return new PageComponent('Shared:Panel', [
            'title' => $this->translator->trans('cms.redirects.incoming.title'),
            'description' => $this->translator->trans($texts.'.description', ['%name%' => $name]),
        ], $children);
    }
}
