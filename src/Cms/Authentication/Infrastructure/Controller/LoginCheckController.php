<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Routing placeholder for the form login check path.
 *
 * The request is intercepted by the form login authenticator before this
 * controller is invoked.
 */
final class LoginCheckController extends AbstractController
{
    #[Route('/login', name: 'cms_login_check', methods: ['POST'])]
    public function __invoke(): never
    {
        throw new \LogicException('This action is intercepted by the form login authenticator.');
    }
}
