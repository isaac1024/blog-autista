<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Controller;

use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Application\SettingsUpdater;
use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class SettingsUpdateController extends AbstractController
{
    #[Route('/settings', name: 'cms_settings_update', methods: ['POST'])]
    #[IsCsrfTokenValid('cms_settings_update')]
    public function __invoke(Request $request, SettingsRepository $settingsRepository, EventBus $eventBus): Response
    {
        try {
            new SettingsUpdater(
                $request->request->getString('name'),
                $request->request->getString('default_image'),
                $request->request->getString('author'),
                // Each entry posts its fields under its position, in order.
                $request->request->all('main_menu'),
                $request->request->all('footer_menu'),
            )($settingsRepository, $eventBus);
        } catch (SettingsNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told on the page it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_settings', status: Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.settings.saved', 'parameters' => []]);

        return $this->redirectToRoute('cms_settings', status: Response::HTTP_SEE_OTHER);
    }
}
