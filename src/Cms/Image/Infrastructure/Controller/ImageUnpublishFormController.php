<?php

declare(strict_types=1);

namespace Cms\Image\Infrastructure\Controller;

use Cms\Image\Application\ImageFinder;
use Cms\Image\Application\ImageNotFound;
use Cms\Image\Domain\ImageRepository;
use Cms\Redirect\Domain\Resource;
use Cms\Redirect\Infrastructure\Controller\RetirementForm;
use Shared\Infrastructure\Ui\CmsLayout;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where an image is taken off the site: it asks what becomes of its
 * addresses, and of each redirect that leads to it, before it goes. A choice
 * refused comes back as it was made.
 */
final class ImageUnpublishFormController extends AbstractController
{
    /**
     * Where the choices last sent and refused wait for the form, by image.
     */
    public const string SAVED = 'cms.images.unpublish.%s';

    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly RetirementForm $retirementForm,
    ) {}

    #[Route('/images/{id}/unpublish', name: 'cms_images_unpublish_form', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function __invoke(string $id, Request $request, ImageRepository $imageRepository): Response
    {
        try {
            $image = new ImageFinder($id)($imageRepository);
        } catch (ImageNotFound $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        if (!$image->canBeUnpublished) {
            $this->addFlash('error', ['key' => 'errors.image.publication.not_published', 'parameters' => []]);

            return $this->redirectToRoute('cms_images_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $saved = $request->getSession()->remove(\sprintf(self::SAVED, $id));
        $title = $this->translator->trans('cms.images.unpublish.page_title', ['%alt%' => $image->alt]);

        $layout = CmsLayout::page(\sprintf('%s · CMS', $title))
            ->withMain('Shared:PageTitle', [
                'title' => $title,
                'subtitle' => $this->translator->trans('cms.redirects.retire.subtitle'),
            ], [
                new PageComponent('Shared:ButtonLink', [
                    'url' => $this->generateUrl('cms_images_edit', ['id' => $id]),
                    'label' => $this->translator->trans('cms.images.unpublish.back'),
                ]),
            ]);

        foreach (($this->retirementForm)(
            Resource::image($id),
            $this->generateUrl('cms_images_unpublish', ['id' => $id]),
            'cms_images_unpublish',
            $this->translator->trans('cms.images.unpublish.submit'),
            \is_array($saved) ? $saved : [],
        ) as $component) {
            $layout = $layout->withMain($component->name, $component->props, $component->children);
        }

        return $this->pageRenderer->render($layout);
    }
}
