<?php

declare(strict_types=1);

namespace Cms\Authentication\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * The HTTP password every request to the CMS host asks before anything else
 * of the app, its login form included: a bot finds nothing to try there but
 * the browser's own question. Previews, whose signed links an editor hands
 * to others, and robots.txt stay open.
 *
 * Wrong passwords from one address past a few are refused without checking
 * them, since a hash is slow to check on purpose. Without its settings there
 * is no gate, which production refuses rather than serving the CMS open.
 *
 * It runs after the redirect to https, so the password never travels in the
 * clear, and before the router.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final readonly class CmsGate
{
    private const string OPEN = '#^/(preview/|robots\.txt$)#';

    public function __construct(
        #[Autowire(env: 'CMS_HOST')]
        private string $cmsHost,
        #[Autowire(env: 'CMS_GATE_USER')]
        private string $user,
        #[Autowire(env: 'CMS_GATE_PASSWORD_HASH')]
        private string $passwordHash,
        #[Autowire('%kernel.environment%')]
        private string $environment,
        #[Target('cms_gate')]
        private RateLimiterFactoryInterface $wrongPasswords,
        private PasswordHasherFactoryInterface $passwordHashers,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || $request->getHost() !== $this->cmsHost || 1 === preg_match(self::OPEN, $request->getPathInfo())) {
            return;
        }

        if ('' === $this->user || '' === $this->passwordHash) {
            if ('prod' === $this->environment) {
                throw new \InvalidArgumentException('CMS_GATE_USER and CMS_GATE_PASSWORD_HASH must not be empty in production.');
            }

            return;
        }

        $response = $this->refusal($request);

        if (null !== $response) {
            $event->setResponse($response);
        }
    }

    /**
     * Why the request may not go on, or null when it gave the password.
     */
    private function refusal(Request $request): ?Response
    {
        $user = (string) $request->getUser();
        $password = (string) $request->getPassword();

        if ('' === $user && '' === $password) {
            return self::unauthorized();
        }

        $limiter = $this->wrongPasswords->create($request->getClientIp() ?? '');

        if (0 === $limiter->consume(0)->getRemainingTokens()) {
            return new Response('Too many wrong passwords; try again later.', Response::HTTP_TOO_MANY_REQUESTS, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $verified = $this->passwordHashers->getPasswordHasher(PasswordAuthenticatedUserInterface::class)->verify($this->passwordHash, $password);

        if (hash_equals($this->user, $user) && $verified) {
            return null;
        }

        $limiter->consume();

        return self::unauthorized();
    }

    private static function unauthorized(): Response
    {
        return new Response('The CMS asks for its password.', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="CMS", charset="UTF-8"',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
