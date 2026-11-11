<?php

declare(strict_types=1);

namespace Web\Tests\Redirect\Infrastructure\Persistence;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Redirect\Domain\RedirectRepository;
use Web\Tests\Redirect\Domain\RedirectMother;

/**
 * The site reads the redirects as the CMS keeps them, from its table.
 */
final class DoctrineRedirectRepositoryTest extends KernelTestCase
{
    private const string OTHER_ORIGIN = '0196f3a0-0000-7000-8000-0000000000a3';

    public function testItReadsTheRedirectAtAnAddress(): void
    {
        $this->keep('0196f3a0-0000-7000-8000-0000000000e1', '/vieja.html', 'page', RedirectMother::PAGE, RedirectMother::ORIGIN);
        $this->keep('0196f3a0-0000-7000-8000-0000000000e2', '/retirada.html', null, null);

        self::assertEquals(RedirectMother::create(originId: RedirectMother::ORIGIN), $this->repository()->findBySource('/vieja.html'));
        self::assertEquals(RedirectMother::gone(), $this->repository()->findBySource('/retirada.html'));
        self::assertNull($this->repository()->findBySource('/ninguna.html'));
    }

    /**
     * Of the redirects that know the resources as their origin, the one
     * changed last comes first, and those of another kind of resource never.
     */
    public function testItReadsTheRedirectsThatKnowTheResourcesAsTheirOriginChangedLastFirst(): void
    {
        $this->keep('0196f3a0-0000-7000-8000-0000000000e1', '/primera.html', null, null, RedirectMother::ORIGIN, updatedAt: '2026-11-09 10:00:00');
        $this->keep('0196f3a0-0000-7000-8000-0000000000e2', '/segunda.html', 'page', RedirectMother::PAGE, RedirectMother::ORIGIN, updatedAt: '2026-11-09 11:00:00');
        $this->keep('0196f3a0-0000-7000-8000-0000000000e3', '/tercera.html', null, null, self::OTHER_ORIGIN, updatedAt: '2026-11-09 09:00:00');
        $this->keep('0196f3a0-0000-7000-8000-0000000000e4', '/media/2026/11/'.RedirectMother::IMAGE, null, null, RedirectMother::ORIGIN, originType: 'image');

        self::assertEquals([
            RedirectMother::create(originId: RedirectMother::ORIGIN),
            RedirectMother::gone(originId: RedirectMother::ORIGIN),
            RedirectMother::gone(originId: self::OTHER_ORIGIN),
        ], $this->repository()->findByOrigins('page', [RedirectMother::ORIGIN, self::OTHER_ORIGIN]));
        self::assertSame([], $this->repository()->findByOrigins('page', []));
    }

    /**
     * Of two changed at once, the one created last comes first.
     */
    public function testItReadsTheRedirectCreatedLastFirstOfTwoChangedAtOnce(): void
    {
        $this->keep('0196f3a0-0000-7000-8000-0000000000e1', '/primera.html', null, null, RedirectMother::ORIGIN);
        $this->keep('0196f3a0-0000-7000-8000-0000000000e2', '/segunda.html', 'page', RedirectMother::PAGE, RedirectMother::ORIGIN);

        self::assertEquals([
            RedirectMother::create(originId: RedirectMother::ORIGIN),
            RedirectMother::gone(originId: RedirectMother::ORIGIN),
        ], $this->repository()->findByOrigins('page', [RedirectMother::ORIGIN]));
    }

    /**
     * A redirect as the CMS keeps it.
     */
    private function keep(string $id, string $source, ?string $targetType, ?string $targetId, ?string $originId = null, string $originType = 'page', string $updatedAt = '2026-11-09 10:00:00'): void
    {
        self::getContainer()->get(Connection::class)->insert('cms_redirect', [
            'id' => $id, 'source' => $source, 'target_type' => $targetType, 'target_id' => $targetId,
            'origin_type' => null === $originId ? null : $originType, 'origin_id' => $originId,
            'created_at' => '2026-11-09 09:00:00', 'updated_at' => $updatedAt, 'version' => 1,
        ]);
    }

    private function repository(): RedirectRepository
    {
        return self::getContainer()->get(RedirectRepository::class);
    }
}
