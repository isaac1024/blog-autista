<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Validates the token of a state-changing CMS request against its route name.
 */
final readonly class CsrfRequestGuard
{
    public function __construct(private CsrfTokenManagerInterface $tokens) {}

    public function assertValid(Request $request): void
    {
        $route = $request->attributes->getString('_route');
        $token = $request->request->getString('_token');

        if ('' === $route || !$this->tokens->isTokenValid(new CsrfToken($route, $token))) {
            throw new AccessDeniedHttpException('The CSRF token is missing or invalid.');
        }
    }
}
