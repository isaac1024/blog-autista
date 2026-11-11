<?php

declare(strict_types=1);

namespace Cms\Redirect\Infrastructure\Controller;

use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\IncomingDecision;
use Cms\Redirect\Domain\InvalidRedirect;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the form that takes a resource off the site posts: the choice for
 * its own address under "address", and that for each redirect that leads
 * to it under "incoming[<id>]".
 */
final readonly class RetirementRequest
{
    /**
     * The choice that leaves its address with nothing, so it isn't found.
     */
    public const string NOT_FOUND = 'none';

    /**
     * The choice that leads a redirect where the address of the resource will.
     */
    public const string SAME = 'same';

    public const string DELETE = 'delete';

    /**
     * Where the request says the address of the resource leads once it is
     * off the site, or null to leave it not found.
     *
     * @throws InvalidRedirect when nothing is chosen for it
     */
    public static function address(Request $request): ?TargetInput
    {
        $values = RedirectTargetField::fromRequest($request, 'address');

        if (self::NOT_FOUND === $values['action']) {
            return null;
        }

        if ('' === $values['action']) {
            throw InvalidRedirect::undecidedAddress();
        }

        return RedirectTargetField::target($values);
    }

    /**
     * What the request says of each redirect that leads to the resource, by
     * its id; one with nothing chosen is left out.
     *
     * @return array<string, IncomingDecision>
     */
    public static function incoming(Request $request): array
    {
        $decisions = [];

        foreach ($request->request->all('incoming') as $id => $fields) {
            $values = RedirectTargetField::values(\is_array($fields) ? $fields : []);

            $decision = match ($values['action']) {
                '' => null,
                self::SAME => IncomingDecision::sameAsAddress(),
                self::DELETE => IncomingDecision::delete(),
                default => IncomingDecision::leadingTo(RedirectTargetField::target($values)->target()),
            };

            if (null !== $decision) {
                // PHP turns an id made of digits into a number.
                $decisions[(string) $id] = $decision;
            }
        }

        return $decisions;
    }
}
