<?php

declare(strict_types=1);

namespace Cms\Redirect\Domain;

use Shared\Domain\DomainError;

final class InvalidRedirect extends DomainError
{
    public static function notAnImageCopy(string $url): self
    {
        return new self(\sprintf('"%s" is under /media/ but names no copy of an image.', $url), 'errors.redirect.not_an_image_copy', ['%url%' => $url]);
    }

    public static function liveSource(Source $source): self
    {
        return new self(\sprintf('The site already answers "%s".', $source->shown()), 'errors.redirect.live_source', ['%source%' => $source->shown()]);
    }

    public static function sourceTaken(Source $source): self
    {
        return new self(\sprintf('"%s" already has a redirect.', $source->shown()), 'errors.redirect.source_taken', ['%source%' => $source->shown()]);
    }

    public static function unknownTargetType(string $type): self
    {
        return new self(\sprintf('A redirect leads to no "%s".', $type), 'errors.redirect.unknown_target_type', ['%type%' => $type]);
    }

    public static function missingTarget(): self
    {
        return new self('Choose where the redirect leads.', 'errors.redirect.missing_target', []);
    }

    public static function undecided(Source $source): self
    {
        return new self(\sprintf('Choose what "%s" does once its target is off the site.', $source->shown()), 'errors.redirect.undecided', ['%source%' => $source->shown()]);
    }

    public static function toItself(): self
    {
        return new self('What is taken off the site can\'t be where its own address leads.', 'errors.redirect.to_itself', []);
    }

    public static function undecidedAddress(): self
    {
        return new self('Choose what the address does once it is off the site.', 'errors.redirect.undecided_address', []);
    }

    public static function noNewTarget(): self
    {
        return new self('Only when its address leads somewhere do the redirects to a resource follow it there.', 'errors.redirect.no_new_target', []);
    }

    public static function targetOffSite(): self
    {
        return new self('A redirect only leads to a published resource.', 'errors.redirect.target_off_site', []);
    }

    public static function imageToOther(): self
    {
        return new self('The address of an image only leads to another image.', 'errors.redirect.image_to_other', []);
    }

    public static function otherToImage(): self
    {
        return new self('Only the address of an image leads to an image.', 'errors.redirect.other_to_image', []);
    }
}
