<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Controller;

use Shared\Infrastructure\Ui\Page\Page;
use Shared\Infrastructure\Ui\Page\PageComponent;
use Shared\Infrastructure\Ui\Page\PageRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LoginController extends AbstractController
{
    public function __construct(
        private readonly PageRenderer $pageRenderer,
        private readonly TranslatorInterface $translator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route('/login', name: 'cms_login', methods: ['GET'])]
    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->pageRenderer->render($this->page($authenticationUtils));
    }

    /**
     * Composes the whole page from Twig components, so it is long by design.
     *
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    private function page(AuthenticationUtils $authenticationUtils): Page
    {
        $error = $authenticationUtils->getLastAuthenticationError();

        $fields = [];

        if (null !== $error) {
            $fields[] = new PageComponent('Shared:Alert', [
                'title' => $this->translator->trans('cms.login.error'),
                'message' => $this->translator->trans(
                    $error->getMessageKey(),
                    $error->getMessageData(),
                    'security',
                ),
            ]);
        }

        $fields[] = new PageComponent('Shared:Form', [
            'action' => $this->generateUrl('cms_login_check'),
            'method' => 'post',
        ], [
            new PageComponent('Shared:FormField', [], [
                new PageComponent('Shared:FormLabel', [
                    'for' => 'username',
                    'text' => $this->translator->trans('cms.login.username'),
                ]),
                new PageComponent('Shared:FormInput', [
                    'name' => '_username',
                    'id' => 'username',
                    'value' => $authenticationUtils->getLastUsername(),
                    'autocomplete' => 'username',
                    'attributes' => [
                        'required' => true,
                        'autofocus' => true,
                    ],
                ]),
            ]),
            new PageComponent('Shared:FormField', [], [
                new PageComponent('Shared:FormLabel', [
                    'for' => 'password',
                    'text' => $this->translator->trans('cms.login.password'),
                ]),
                new PageComponent('Shared:FormInput', [
                    'name' => '_password',
                    'id' => 'password',
                    'type' => 'password',
                    'autocomplete' => 'current-password',
                    'attributes' => [
                        'required' => true,
                    ],
                ]),
            ]),
            new PageComponent('Shared:FormInput', [
                'name' => '_csrf_token',
                'type' => 'hidden',
                'value' => $this->csrfTokenManager->getToken('authenticate')->getValue(),
            ]),
            new PageComponent('Shared:FormButton', [
                'label' => $this->translator->trans('cms.login.submit'),
                'fullWidth' => true,
            ]),
        ]);

        // A single card in the middle of the screen: nothing else asks for attention.
        return Page::create(\sprintf(
            '%s · %s',
            $this->translator->trans('cms.login.title'),
            $this->translator->trans('cms.login.brand'),
        ))
            ->withMainClass('grid min-h-dvh content-center justify-items-center bg-bg p-[clamp(1rem,5vw,3rem)] [&>*]:w-[min(100%,28rem)]')
            ->withMain('Shared:Panel', [], [
                new PageComponent('Shared:Brand', [
                    'brand' => $this->translator->trans('cms.login.brand'),
                    'attributes' => ['class' => 'text-lg text-accent'],
                ]),
                new PageComponent('Shared:PageTitle', [
                    'title' => $this->translator->trans('cms.login.title'),
                    'subtitle' => $this->translator->trans('cms.login.subtitle'),
                    'variant' => 'login',
                ]),
                ...$fields,
            ]);
    }
}
