<?php

declare(strict_types=1);

namespace Shared\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Keeps the time in UTC, whatever time zone the php.ini of the server
     * says (Europe/Berlin on Hetzner): Doctrine stores a date as the clock
     * reads it, without a zone, so the web and the cron must read the same
     * one. The blog tells its days in Madrid apart, by SiteTimeZone.
     */
    #[\Override]
    public function boot(): void
    {
        date_default_timezone_set('UTC');

        parent::boot();
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
