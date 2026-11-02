<?php

declare(strict_types=1);

namespace Cms\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'cms_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response('CMS context is up.');
    }
}
