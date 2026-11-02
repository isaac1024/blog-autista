<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    #[Route('/logout', name: 'cms_logout', methods: ['GET'])]
    public function __invoke(): never
    {
        throw new \LogicException('This action is intercepted by the logout key on the CMS firewall.');
    }
}
