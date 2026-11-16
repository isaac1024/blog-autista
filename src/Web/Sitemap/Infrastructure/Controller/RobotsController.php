<?php

declare(strict_types=1);

namespace Web\Sitemap\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Lets search engines through the whole site, and tells them where its
 * sitemap is.
 */
final class RobotsController extends AbstractController
{
    #[Route('/robots.txt', name: 'web_robots', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response(
            \sprintf("User-agent: *\nAllow: /\n\nSitemap: %s\n", $this->generateUrl('web_sitemap_index', referenceType: UrlGeneratorInterface::ABSOLUTE_URL)),
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
