<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Infrastructure\Event;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Redirect\Domain\CmsRedirectDeleted;
use Web\Redirect\Domain\CmsRedirectSaved;
use Web\Redirect\Domain\RedirectId;
use Web\Redirect\Domain\RedirectRepository;
use Web\Redirect\Infrastructure\Event\CmsRedirectDeletedSubscriber;
use Web\Redirect\Infrastructure\Event\CmsRedirectSavedSubscriber;

/**
 * The site takes the redirects the CMS saves and deletes, whatever order
 * their events come in, and however many times: what the CMS last did to
 * each one is what the site has.
 */
final class RedirectProjectionTest extends KernelTestCase
{
    private const string FIRST = '0196f3a0-0000-7000-8000-0000000000e1';
    private const string SECOND = '0196f3a0-0000-7000-8000-0000000000e2';
    private const string PAGE_A = '0196f3a0-0000-7000-8000-0000000000a1';
    private const string PAGE_B = '0196f3a0-0000-7000-8000-0000000000b1';

    public function testItKeepsTheLastChangeWhenAnOlderOneComesLate(): void
    {
        $this->saved(self::FIRST, self::PAGE_A, 1, '10:00');
        $this->saved(self::FIRST, self::PAGE_B, 2, '10:05');

        $this->saved(self::FIRST, self::PAGE_A, 1, '10:00');

        self::assertSame(self::PAGE_B, $this->targetAt('/vieja.html'));
    }

    public function testItTakesTheSameChangeTwiceAsOnce(): void
    {
        $this->saved(self::FIRST, self::PAGE_A, 1, '10:00');
        $this->deleted(self::FIRST, 2, '10:05');

        $this->deleted(self::FIRST, 2, '10:05');

        self::assertNull(self::getContainer()->get(RedirectRepository::class)->findBySource('/vieja.html'));
    }

    /**
     * A change to a redirect deleted since never brings it back.
     */
    public function testItNeverBringsBackARedirectDeletedWhenAnOlderChangeComesLate(): void
    {
        $this->saved(self::FIRST, self::PAGE_A, 1, '10:00');
        $this->deleted(self::FIRST, 3, '10:10');

        $this->saved(self::FIRST, self::PAGE_B, 2, '10:05');

        self::assertNull(self::getContainer()->get(RedirectRepository::class)->find(new RedirectId(self::FIRST)));
    }

    /**
     * The CMS deleted one redirect and created another at its address; the
     * site takes the new one first, then the deletion of the old.
     */
    public function testItAnswersAnAddressWithTheNewRedirectWhenTheDeletionOfTheOldComesLast(): void
    {
        $this->saved(self::FIRST, self::PAGE_A, 1, '10:00');
        $this->saved(self::SECOND, self::PAGE_B, 1, '10:10');

        self::assertSame(self::PAGE_B, $this->targetAt('/vieja.html'));

        $this->deleted(self::FIRST, 2, '10:09');

        self::assertSame(self::PAGE_B, $this->targetAt('/vieja.html'));
    }

    private function saved(string $id, ?string $target, int $revision, string $time): void
    {
        self::getContainer()->get(CmsRedirectSavedSubscriber::class)->handle(new CmsRedirectSaved(
            $id,
            '/vieja.html',
            null === $target ? null : 'page',
            $target,
            null,
            null,
            \sprintf('saved-%s-%d', $id, $revision),
            new \DateTimeImmutable('2026-11-09 '.$time),
            $revision,
        ));
    }

    private function deleted(string $id, int $revision, string $time): void
    {
        self::getContainer()->get(CmsRedirectDeletedSubscriber::class)->handle(new CmsRedirectDeleted(
            $id,
            \sprintf('deleted-%s-%d', $id, $revision),
            new \DateTimeImmutable('2026-11-09 '.$time),
            $revision,
        ));
    }

    private function targetAt(string $source): ?string
    {
        return self::getContainer()->get(RedirectRepository::class)->findBySource($source)?->targetId();
    }
}
