<?php

declare(strict_types=1);

namespace Web\Analytics\Domain;

enum Device: string
{
    case Mobile = 'mobile';
    case Desktop = 'desktop';
}
