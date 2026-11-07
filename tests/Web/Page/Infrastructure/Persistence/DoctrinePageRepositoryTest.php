<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Page\Domain\Robots;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class DoctrinePageRepositoryTest extends KernelTestCase
{
    public function testItSavesThePageSoItCanBeReadBack(): void
    {
        $page = Page::publish(CmsPagePublishedMother::create(metaDescription: null, robots: Robots::NoindexNofollow));

        $this->repository()->save($page);
        $this->entityManager()->clear();

        self::assertEquals($page, $this->repository()->find(PageIdMother::create()));
    }

    public function testItFindsThePageAtTheSlug(): void
    {
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Contacto', slug: 'contacto', type: PageType::Contact)));
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Sobre mí', slug: 'sobre-mi')));
        $this->entityManager()->clear();

        self::assertSame('Sobre mí', $this->repository()->findBySlug('sobre-mi')?->title());
        self::assertNull($this->repository()->findBySlug('aviso-legal'));
    }

    public function testItFindsTheHomePageByItsEmptySlug(): void
    {
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Inicio', slug: '', type: PageType::Home)));
        $this->entityManager()->clear();

        self::assertSame('Inicio', $this->repository()->findBySlug('')?->title());
    }

    public function testItDeletesOnlyTheGivenPage(): void
    {
        $kept = Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), slug: 'contacto'));
        $deleted = Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), slug: 'sobre-mi'));
        $this->repository()->save($kept);
        $this->repository()->save($deleted);

        $this->repository()->delete($deleted);
        $this->entityManager()->clear();

        self::assertNull($this->repository()->findBySlug('sobre-mi'));
        self::assertNotNull($this->repository()->findBySlug('contacto'));
    }

    private function repository(): PageRepository
    {
        return self::getContainer()->get(PageRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
