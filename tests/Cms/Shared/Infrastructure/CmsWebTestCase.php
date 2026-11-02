<?php

declare(strict_types=1);

namespace Cms\Tests\Shared\Infrastructure;

use Cms\Authentication\Infrastructure\Security\AdministratorUser;
use Cms\Tests\Authentication\Domain\AdministratorMother;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base for HTTP tests of the CMS. Authenticated clients get their session
 * straight from the firewall, without going through the login form.
 */
abstract class CmsWebTestCase extends WebTestCase
{
    private const string FIREWALL = 'cms';

    protected static function createAuthenticatedClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(AdministratorUser::fromAdministrator(AdministratorMother::create()), self::FIREWALL);

        return $client;
    }
}
