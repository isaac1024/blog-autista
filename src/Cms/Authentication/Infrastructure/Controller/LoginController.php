<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * The form that signs an editor in, as LoginForm composes it, with what the
 * last attempt left.
 */
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly LoginForm $loginForm,
    ) {}

    #[Route('/login', name: 'cms_login', methods: ['GET'])]
    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->pageRenderer->render(($this->loginForm)(
            $authenticationUtils->getLastAuthenticationError(),
            $authenticationUtils->getLastUsername(),
        ));
    }
}
