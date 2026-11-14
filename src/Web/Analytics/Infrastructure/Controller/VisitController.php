<?php

declare(strict_types=1);

namespace Web\Analytics\Infrastructure\Controller;

use Shared\Domain\DomainError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Web\Analytics\Application\ContentNotPublished;
use Web\Analytics\Application\VisitRecorder;
use Web\Analytics\Domain\DailySalt;
use Web\Analytics\Domain\PublishedContents;
use Web\Analytics\Domain\VisitId;
use Web\Analytics\Domain\VisitRepository;

/**
 * Takes the visit a page of the site sends once its reader sees it. Its
 * address names nothing a blocker looks for, and the answer is never kept by
 * a cache along the way, nor sets a cookie.
 *
 * A visit that breaks a rule of the domain is refused as unprocessable: it
 * comes from no form, so there is no page to tell it on.
 */
final class VisitController extends AbstractController
{
    public function __construct(
        private readonly VisitRateLimit $visitRateLimit,
        private readonly ClientAddressWarning $clientAddressWarning,
        #[Autowire('%env(WEB_HOST)%')]
        private readonly string $webHost,
        #[Autowire('%env(CMS_HOST)%')]
        private readonly string $cmsHost,
    ) {}

    #[Route('/v', name: 'web_visit', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload(acceptFormat: 'form')]
        VisitPayload $payload,
        Request $request,
        VisitRepository $visitRepository,
        PublishedContents $publishedContents,
        DailySalt $dailySalt,
    ): Response {
        if (!SameOriginRequest::isFromSite($request)) {
            throw new AccessDeniedHttpException('A visit is only taken from a page of the site.');
        }

        $address = $request->getClientIp() ?? '';
        ($this->clientAddressWarning)($request->getClientIp());

        if (!$this->visitRateLimit->accepts($address)) {
            // A page sends its visit once, and never again: when to retry is of no use.
            throw new TooManyRequestsHttpException();
        }

        try {
            new VisitRecorder(
                VisitId::random()->value(),
                $payload->type,
                $payload->content,
                $payload->referrer,
                $payload->source,
                $payload->medium,
                $payload->zone,
                $address,
                $request->headers->get('User-Agent') ?? '',
                [$this->webHost, $this->cmsHost],
            )($visitRepository, $publishedContents, $dailySalt);
        } catch (ContentNotPublished $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        } catch (DomainError $error) {
            throw new UnprocessableEntityHttpException($error->getMessage(), $error);
        }

        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
