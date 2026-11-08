<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Controller;

use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Application\SettingsPublisher;
use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsPublishController extends AbstractController
{
    #[Route('/settings/publish', name: 'cms_settings_publish', methods: ['POST'])]
    public function __invoke(SettingsRepository $settingsRepository, EventBus $eventBus): Response
    {
        try {
            new SettingsPublisher()($settingsRepository, $eventBus);
        } catch (SettingsNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told in the editor, where it goes back to all the same.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);
        }

        return $this->redirectToRoute('cms_settings', status: Response::HTTP_SEE_OTHER);
    }
}
