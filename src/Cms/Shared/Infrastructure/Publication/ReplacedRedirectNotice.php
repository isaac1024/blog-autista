<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Publication;

use Cms\Redirect\Application\PublicationOutlook;
use Cms\Shared\Infrastructure\Ui\RedirectNames;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells that publishing deletes the redirect at the address the resource
 * takes, and what else follows when that address was the last of another
 * resource: what links to it stops showing it.
 */
final readonly class ReplacedRedirectNotice
{
    public function __construct(
        private RedirectNames $redirectNames,
        private TranslatorInterface $translator,
    ) {}

    public function __invoke(PublicationOutlook $outlook): ?PageComponent
    {
        $replaced = $outlook->replaced;

        if (null === $replaced) {
            return null;
        }

        $names = ($this->redirectNames)([$replaced])[$replaced->id];
        $parameters = ['%address%' => $replaced->source, '%target%' => $names['target']];

        return new PageComponent('Shared:Notice', [
            'message' => null === $names['origin']
                ? $this->translator->trans('cms.redirects.publish.replaced', $parameters)
                : $this->translator->trans('cms.redirects.publish.replaced_origin', [...$parameters, '%origin%' => $names['origin']]),
        ]);
    }
}
