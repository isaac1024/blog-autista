<?php

declare(strict_types=1);

namespace Cms\Settings\Application;

final class SettingsNotFound extends \RuntimeException
{
    public static function create(): self
    {
        return new self('The site has no settings yet; create them from the console.');
    }
}
