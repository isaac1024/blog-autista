<?php

declare(strict_types=1);

namespace Cms\External\Infrastructure\Controller;

use Cms\External\Application\ExternalResourceResponse;
use Shared\Domain\SiteTimeZone;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tells editors where an external resource stands: whether it is on the
 * site, whether the site shows what they last saved, and whether its
 * address still leads to it.
 */
final readonly class ExternalResourceState
{
    public function __construct(private TranslatorInterface $translator) {}

    /**
     * Short name of the state, for a badge.
     */
    public function label(ExternalResourceResponse $resource): string
    {
        return $this->translator->trans(\sprintf('cms.externals.status.%s.label', $this->key($resource)));
    }

    /**
     * What the state means for the site, next to the actions that change it.
     */
    public function description(ExternalResourceResponse $resource): string
    {
        return $this->translator->trans(\sprintf('cms.externals.status.%s.description', $this->key($resource)));
    }

    /**
     * What the resource is, and who embeds it, if anyone, such as "Vídeo de YouTube".
     */
    public function kind(ExternalResourceResponse $resource): string
    {
        $kind = $this->translator->trans(\sprintf('cms.externals.kind.%s', $resource->kind));

        return null === $resource->provider ? $kind : $this->translator->trans('cms.externals.kind_by', ['%kind%' => $kind, '%provider%' => $this->translator->trans(\sprintf('cms.externals.provider.%s', $resource->provider))]);
    }

    /**
     * When the resource was last changed, in the editor's language.
     */
    public function lastUpdate(ExternalResourceResponse $resource): string
    {
        $formatter = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, SiteTimeZone::NAME);

        return $this->translator->trans('cms.externals.list.updated_at', ['%date%' => $formatter->format($resource->updatedAt)]);
    }

    private function key(ExternalResourceResponse $resource): string
    {
        if ($resource->isPublished() && !$resource->available) {
            return 'unavailable';
        }

        return $resource->changed ? 'changed' : $resource->status;
    }
}
