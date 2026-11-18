<?php

declare(strict_types=1);

namespace Cms\Shared\Infrastructure\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The CMS has no screen of its own at its root: it opens on the posts, which
 * are what it is used for most. The redirect is temporary, so the root can
 * become a screen later without browsers remembering the old answer.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'cms_home', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        return $this->redirectToRoute('cms_posts');
    }
}
