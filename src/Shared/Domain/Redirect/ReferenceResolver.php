<?php

declare(strict_types=1);

namespace Shared\Domain\Redirect;

/**
 * What a page painted like the site's shows of the resources it refers to,
 * such as the pages it links to or the images it embeds, by their ids: each
 * one on the site as it is; one off it, the resource its last address now
 * redirects to, if that is on the site; and nothing when that address is
 * gone, has no redirect, or leads elsewhere than to a resource of the kind.
 *
 * It follows one redirect at most: a redirect only leads to a resource on
 * the site, so one more would lead nowhere new.
 *
 * The site and the preview of the CMS both paint with it, each from what it
 * has, so what is previewed is what is published.
 */
final readonly class ReferenceResolver
{
    /**
     * @param string       $type   the kind of resource referred to, such as "page"
     * @param list<string> $ids    the resources referred to
     * @param ?string      $except a resource never shown in place of another, such as the page painted, which doesn't link itself
     */
    public function __construct(
        private string $type,
        private array $ids,
        private ?string $except,
    ) {}

    /**
     * @template T
     *
     * @param \Closure(list<string>): array<string, T> $onSite those among the ids that are on the site, keyed by id
     *
     * @return array<string, T> keyed by the id referred to, whatever is shown for it
     */
    public function __invoke(\Closure $onSite, RedirectsByOrigin $redirectsByOrigin): array
    {
        $ids = array_values(array_unique($this->ids));
        $found = $onSite($ids);
        $missing = array_values(array_filter($ids, static fn (string $id): bool => !\array_key_exists($id, $found)));

        if ([] === $missing) {
            return $found;
        }

        $targets = [];

        foreach ($redirectsByOrigin->targetsOf($this->type, $missing) as $origin => $target) {
            if (null !== $target && $this->type === $target['type'] && $this->except !== $target['id']) {
                $targets[$origin] = $target['id'];
            }
        }

        $redirected = [] === $targets ? [] : $onSite(array_values(array_unique($targets)));

        foreach ($targets as $origin => $target) {
            if (\array_key_exists($target, $redirected)) {
                $found[$origin] = $redirected[$target];
            }
        }

        return $found;
    }
}
