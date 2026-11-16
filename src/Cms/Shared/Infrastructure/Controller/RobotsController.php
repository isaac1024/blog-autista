<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Keeps search engines out of the whole CMS, whose screens are behind its
 * login, and whose previews ask not to be indexed anyway.
 */
final class RobotsController extends AbstractController
{
    #[Route('/robots.txt', name: 'cms_robots', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
