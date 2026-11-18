<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Controller;

use Cms\Tests\Shared\Infrastructure\CmsWebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginControllerTest extends CmsWebTestCase
{
    public function testItServesTheLoginForm(): void
    {
        $client = self::createClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_login');
    }

    public function testItRejectsInvalidCredentials(): void
    {
        $client = self::createLoginClient();

        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', [
            '_username' => 'admin',
            '_password' => 'wrong-password',
        ]);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertRouteSame('cms_login');
    }

    public function testItThrottlesRepeatedFailures(): void
    {
        $client = self::createLoginClient();
        $client->request('GET', 'http://cms.blogautista.localhost/login');

        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $client->submitForm('Sign in', [
                '_username' => 'throttled-admin',
                '_password' => 'wrong-password',
            ]);

            if ($attempt < 6) {
                $client->followRedirect();
            }
        }

        $session = $client->getSession();
        self::assertNotNull($session);
        $error = $session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);
        self::assertInstanceOf(TooManyLoginAttemptsAuthenticationException::class, $error);
    }

    public function testItSignsOutTheSessionsOpenedWithAPasswordThatChanged(): void
    {
        $client = self::createLoginClient();
        $this->signIn($client, 'admin');
        $client->request('GET', 'http://cms.blogautista.localhost/pages');
        self::assertResponseIsSuccessful();

        // Each request boots the kernel again, which reads the hash of the new password.
        $previous = self::changePasswordHash(password_hash('new-password', \PASSWORD_BCRYPT, ['cost' => 4]));

        try {
            $client->request('GET', 'http://cms.blogautista.localhost/pages');
            self::assertResponseRedirects('http://cms.blogautista.localhost/login');

            $this->signIn($client, 'admin');
            self::assertResponseRedirects('http://cms.blogautista.localhost/login', null, 'the old password no longer signs in');

            $this->signIn($client, 'new-password');
            self::assertResponseRedirects('http://cms.blogautista.localhost/pages');
        } finally {
            self::changePasswordHash($previous);
        }
    }

    private function signIn(KernelBrowser $client, string $password): void
    {
        $client->request('GET', 'http://cms.blogautista.localhost/login');
        $client->submitForm('Sign in', ['_username' => 'admin', '_password' => $password]);
    }

    /**
     * @return string the hash it replaces
     */
    private static function changePasswordHash(string $hash): string
    {
        $previous = $_SERVER['CMS_USER_PASSWORD_HASH'];
        \assert(\is_string($previous));
        $_SERVER['CMS_USER_PASSWORD_HASH'] = $_ENV['CMS_USER_PASSWORD_HASH'] = $hash;

        return $previous;
    }
}
