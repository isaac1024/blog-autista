<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\PreviousAddressDecision;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\Resource;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the form that confirms publishing posts: the fingerprint of what it
 * showed under "outlook", and the choice for the address the resource
 * leaves under "previous".
 */
final readonly class PublicationRequest
{
    /**
     * The choice that leads the address it leaves to the resource itself.
     */
    public const string ITSELF = 'itself';

    /**
     * The choice that leaves the address it leaves as it is.
     */
    public const string KEEP = 'keep';

    /**
     * The fingerprint of what the form showed, or null when the request
     * doesn't come from it, so the editor was shown nothing to confirm.
     */
    public static function outlook(Request $request): ?string
    {
        return $request->request->has('outlook') ? $request->request->getString('outlook') : null;
    }

    /**
     * What the request says the address the resource leaves does; undecided
     * when it says nothing, which publishing only accepts while there is none.
     */
    public static function previous(Request $request, Resource $resource): PreviousAddressDecision
    {
        $values = RedirectTargetField::fromRequest($request, 'previous');

        return match ($values['action']) {
            '' => PreviousAddressDecision::undecided(),
            self::KEEP => PreviousAddressDecision::keep(),
            self::ITSELF => PreviousAddressDecision::leadTo(new TargetInput($resource->type()->value, $resource->id())),
            default => PreviousAddressDecision::leadTo(RedirectTargetField::target($values)),
        };
    }
}
