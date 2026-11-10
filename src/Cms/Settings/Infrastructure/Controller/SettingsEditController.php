<?php

declare(strict_types=1);

namespace Cms\Settings\Infrastructure\Controller;

use Cms\Settings\Application\SettingsFinder;
use Cms\Settings\Application\SettingsNotFound;
use Cms\Settings\Domain\SettingsRepository;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Where the settings of the site are edited, as SettingsEditor composes it.
 */
final class SettingsEditController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly SettingsEditor $settingsEditor,
    ) {}

    #[Route('/settings', name: 'cms_settings', methods: ['GET'])]
    public function __invoke(SettingsRepository $settingsRepository): Response
    {
        try {
            $settings = new SettingsFinder()($settingsRepository);
        } catch (SettingsNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        return $this->pageRenderer->render(($this->settingsEditor)($settings));
    }
}
