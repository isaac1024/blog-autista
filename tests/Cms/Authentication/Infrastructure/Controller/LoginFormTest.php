<?php

declare(strict_types=1);

namespace Cms\Tests\Authentication\Infrastructure\Controller;

use Cms\Authentication\Infrastructure\Controller\LoginForm;
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Ui\Page\Page;
use Shared\Tests\Infrastructure\Routing\RouteNameUrlGenerator;
use Shared\Tests\Infrastructure\Translation\CatalogueTranslator;
use Shared\Tests\Infrastructure\Ui\ComponentContracts;
use Shared\Tests\Infrastructure\Ui\PageComponents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The decisions the form that signs an editor in makes: what it posts,
 * with what it fills itself, and why it says the last attempt failed.
 * Routes are generated as their names; the texts are their translation
 * keys, with what their placeholders are given.
 */
final class LoginFormTest extends TestCase
{
    /**
     * It posts what the firewall checks: the name and the password, as
     * browsers fill them in, with the token of the attempt.
     */
    public function testItPostsWhatTheFirewallChecks(): void
    {
        $login = $this->login(null, 'admin');

        $form = PageComponents::one($login, 'Shared:Form', ['action' => '/cms_login_check', 'method' => 'post']);
        $username = PageComponents::one($form, 'Shared:FormInput', ['name' => '_username', 'value' => 'admin', 'autocomplete' => 'username']);
        self::assertSame(['required' => true, 'autofocus' => true], $username->props['attributes']);
        $password = PageComponents::one($form, 'Shared:FormInput', ['name' => '_password', 'type' => 'password', 'autocomplete' => 'current-password']);
        self::assertSame(['required' => true], $password->props['attributes']);
        PageComponents::one($form, 'Shared:FormInput', ['name' => '_csrf_token', 'type' => 'hidden', 'value' => 'token-de-autenticacion']);
        self::assertCount(1, PageComponents::find($form, 'Shared:FormButton', []));
    }

    /**
     * A first attempt is told nothing; a failed one is told why, in the
     * words of the firewall, with what they say.
     */
    public function testItTellsWhyTheLastAttemptFailed(): void
    {
        self::assertSame([], PageComponents::find($this->login(null, ''), 'Shared:Alert', []));

        foreach ([
            [new BadCredentialsException(), 'Invalid credentials.'],
            [new TooManyLoginAttemptsAuthenticationException(3), 'Too many failed login attempts, please try again in %minutes% minutes.(%count%=3, %minutes%=3)'],
        ] as [$error, $message]) {
            PageComponents::one($this->login($error, 'admin'), 'Shared:Alert', ['title' => 'cms.login.error', 'message' => $message]);
        }
    }

    private function login(?AuthenticationException $error, string $lastUsername): Page
    {
        $csrfTokenManager = self::createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturnCallback(
            static fn (string $id): CsrfToken => new CsrfToken($id, 'authenticate' === $id ? 'token-de-autenticacion' : 'otro'),
        );

        $login = new LoginForm(new RouteNameUrlGenerator(), new CatalogueTranslator(), $csrfTokenManager)($error, $lastUsername);
        // Whatever a test asks, the form gives every component what it takes.
        ComponentContracts::assertHonoured($login);

        return $login;
    }
}
