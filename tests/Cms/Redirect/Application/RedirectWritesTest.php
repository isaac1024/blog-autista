<?php

declare(strict_types=1);

namespace Cms\Tests\Redirect\Application;

use Cms\Page\Domain\PageRepository;
use Cms\Redirect\Application\RedirectCreator;
use Cms\Redirect\Application\RedirectDeleter;
use Cms\Redirect\Application\RedirectRetargeter;
use Cms\Redirect\Application\TargetInput;
use Cms\Redirect\Domain\InvalidRedirect;
use Cms\Redirect\Domain\Redirect;
use Cms\Redirect\Domain\RedirectId;
use Cms\Redirect\Domain\RedirectRepository;
use Cms\Redirect\Domain\SiteResources;
use Cms\Tests\Page\Domain\PageMother;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\MutualExclusion;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Two changes to a redirect run one after the other, each on what the other
 * saved, so neither undoes the other. Here the other change is the request
 * that held the address while this one waited for it: it saves just before
 * this one is let through.
 */
final class RedirectWritesTest extends KernelTestCase
{
    private const string REDIRECT = '0196f3a0-0000-7000-8000-0000000000e9';
    private const string PAGE_A = '0196f3a0-0000-7000-8000-0000000000aa';
    private const string PAGE_B = '0196f3a0-0000-7000-8000-0000000000bb';
    private const string HOST = 'www.blogautista.localhost';

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $pages = self::getContainer()->get(PageRepository::class);
        $pages->save(PageMother::published(id: self::PAGE_A, title: 'Página A', slug: 'pagina-a'));
        $pages->save(PageMother::published(id: self::PAGE_B, title: 'Página B', slug: 'pagina-b'));
        new RedirectCreator(self::REDIRECT, 'https://'.self::HOST.'/vieja.html', new TargetInput(null, null), self::HOST)(...$this->ports());
    }

    public function testItLeadsWhatAnotherChangeSavedWhileItWaitedSomewhereElse(): void
    {
        [$redirects, $siteResources] = $this->ports();

        new RedirectRetargeter(self::REDIRECT, new TargetInput('page', self::PAGE_B))($redirects, $siteResources, $this->afterAnotherChange());

        self::assertSame(['version' => 3, 'target_id' => self::PAGE_B], $this->stored());
    }

    public function testItDeletesWhatAnotherChangeSavedWhileItWaited(): void
    {
        new RedirectDeleter(self::REDIRECT)(
            self::getContainer()->get(RedirectRepository::class),
            $this->afterAnotherChange(),
        );

        self::assertNull(self::getContainer()->get(RedirectRepository::class)->find(new RedirectId(self::REDIRECT)));
    }

    /**
     * A change that saves a redirect it read before another change saved it
     * is refused, rather than overwriting what that one saved.
     */
    public function testItRefusesToSaveARedirectReadBeforeAnotherChangeSavedIt(): void
    {
        $stale = $this->readNow();
        $this->changedMeanwhile(version: 2, target: self::PAGE_A);

        $stale->retarget(new TargetInput('page', self::PAGE_B)->target(), self::getContainer()->get(SiteResources::class));

        $this->expectExceptionObject(InvalidRedirect::changedMeanwhile($stale->source()));

        self::getContainer()->get(RedirectRepository::class)->save($stale);
    }

    /**
     * @return array{RedirectRepository, SiteResources, MutualExclusion}
     */
    private function ports(): array
    {
        return [
            self::getContainer()->get(RedirectRepository::class),
            self::getContainer()->get(SiteResources::class),
            self::getContainer()->get(MutualExclusion::class),
        ];
    }

    /**
     * Mutual exclusion as a request that waited for the address finds it:
     * the one that held it saved the redirect, to page A, just before.
     */
    private function afterAnotherChange(): MutualExclusion
    {
        $mutualExclusion = self::getContainer()->get(MutualExclusion::class);
        $meanwhile = fn () => $this->changedMeanwhile(version: 2, target: self::PAGE_A);

        return new readonly class($mutualExclusion, $meanwhile) implements MutualExclusion {
            /**
             * @param \Closure(): void $meanwhile
             */
            public function __construct(
                private MutualExclusion $mutualExclusion,
                private \Closure $meanwhile,
            ) {}

            #[\Override]
            public function run(array $keys, \Closure $work): mixed
            {
                ($this->meanwhile)();

                return $this->mutualExclusion->run($keys, $work);
            }
        };
    }

    /**
     * Reads the redirect, so Doctrine holds it as it is now.
     */
    private function readNow(): Redirect
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->find(Redirect::class, new RedirectId(self::REDIRECT)) ?? self::fail('No redirect to read.');
    }

    /**
     * What another request saving the redirect leaves in the database.
     */
    private function changedMeanwhile(int $version, string $target): void
    {
        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->executeStatement(
            "UPDATE cms_redirect SET version = ?, target_type = 'page', target_id = ? WHERE id = ?",
            [$version, $target, self::REDIRECT],
        );
    }

    /**
     * @return array{version: int, target_id: ?string}
     */
    private function stored(): array
    {
        /** @var array{version: int|string, target_id: ?string} $row */
        $row = self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchAssociative(
            'SELECT version, target_id FROM cms_redirect WHERE id = ?',
            [self::REDIRECT],
        );

        return ['version' => (int) $row['version'], 'target_id' => $row['target_id']];
    }
}
