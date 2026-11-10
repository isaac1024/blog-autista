<?php

declare(strict_types=1);

namespace Web\Analytics\Application;

use Web\Analytics\Domain\Arrival;
use Web\Analytics\Domain\Country;
use Web\Analytics\Domain\DailySalt;
use Web\Analytics\Domain\Day;
use Web\Analytics\Domain\PageId;
use Web\Analytics\Domain\PublishedPages;
use Web\Analytics\Domain\Reader;
use Web\Analytics\Domain\UserAgent;
use Web\Analytics\Domain\Visit;
use Web\Analytics\Domain\VisitId;
use Web\Analytics\Domain\VisitRepository;

/**
 * Keeps a page a reader saw on the site, to be counted when its day is over.
 * A robot's is left out; one of a page off the site is refused.
 *
 * What the browser tells of the visit comes as it sent it, an empty string
 * for what it had none of. Its constructor takes each of it, one parameter
 * each, with where the request came from.
 *
 * @SuppressWarnings("PHPMD.ExcessiveParameterList")
 */
final readonly class VisitRecorder
{
    /**
     * @param list<string> $siteHosts the hosts of the blog, whose pages are within the site
     */
    public function __construct(
        private string $id,
        private string $pageId,
        private string $referrer,
        private string $campaignSource,
        private string $campaignMedium,
        private string $timeZone,
        private string $address,
        private string $userAgent,
        private array $siteHosts,
    ) {}

    public function __invoke(VisitRepository $visitRepository, PublishedPages $publishedPages, DailySalt $dailySalt): void
    {
        $pageId = new PageId($this->pageId);
        $arrival = Arrival::from($this->referrer, $this->campaignSource, $this->campaignMedium, $this->siteHosts);
        $country = Country::ofTimeZone($this->timeZone);
        $userAgent = new UserAgent($this->userAgent);

        if ($userAgent->isRobot()) {
            return;
        }

        if (!$publishedPages->contains($pageId)) {
            throw PageNotPublished::withId($pageId);
        }

        $day = Day::today();

        Visit::record(new VisitId($this->id), $pageId, $day, $arrival, Reader::from($dailySalt->forDay($day), $this->address, $userAgent, $country))
            ->save($visitRepository);
    }
}
