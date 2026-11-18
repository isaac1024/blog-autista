<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Controller;

use Cms\Settings\Application\SettingsChangesDiscarder;
use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Domain\SettingsRepository;
use Shared\Domain\DomainError;
use Shared\Domain\Event\EventBus;
use Shared\Domain\Transaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

final class SettingsDiscardChangesController extends AbstractController
{
    #[Route('/settings/discard', name: 'cms_settings_discard', methods: ['POST'])]
    #[IsCsrfTokenValid('cms_settings_discard')]
    public function __invoke(SettingsRepository $settingsRepository, EventBus $eventBus, Transaction $transaction): Response
    {
        try {
            new SettingsChangesDiscarder()($settingsRepository, $eventBus, $transaction);
        } catch (SettingsNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            // Told in the editor, where it goes back to.
            $this->addFlash('error', ['key' => $error->key(), 'parameters' => $error->parameters()]);

            return $this->redirectToRoute('cms_settings', status: Response::HTTP_SEE_OTHER);
        }

        $this->addFlash('success', ['key' => 'cms.notices.settings.discarded', 'parameters' => []]);

        return $this->redirectToRoute('cms_settings', status: Response::HTTP_SEE_OTHER);
    }
}
