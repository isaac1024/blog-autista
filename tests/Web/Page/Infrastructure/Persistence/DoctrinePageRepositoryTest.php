<?php

declare(strict_types=1);

namespace Web\Tests\Page\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Shared\Tests\Infrastructure\Persistence\Doctrine\MappedTables;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Web\Page\Domain\Page;
use Web\Page\Domain\PageRepository;
use Web\Page\Domain\PageType;
use Web\Shared\Domain\Robots;
use Web\Tests\Page\Domain\CmsPagePublishedMother;
use Web\Tests\Page\Domain\PageIdMother;

final class DoctrinePageRepositoryTest extends KernelTestCase
{
    use MappedTables;

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

    public function testItFindsTheHomePageByItsType(): void
    {
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), slug: 'sobre-mi')));
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Inicio', slug: null, type: PageType::Home)));
        $this->entityManager()->clear();

        self::assertSame('Inicio', $this->repository()->findHome()?->title());
    }

    public function testItFindsNoHomePageUntilItIsOnTheSite(): void
    {
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), slug: 'sobre-mi')));
        $this->entityManager()->clear();

        self::assertNull($this->repository()->findHome());
    }

    public function testItFindsThePageThatSearchesByItsType(): void
    {
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), slug: 'sobre-mi')));
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Buscar', slug: 'buscar', type: PageType::Search)));
        $this->entityManager()->clear();

        self::assertSame('buscar', $this->repository()->findSearch()?->slug());
    }

    public function testItFindsNoPageThatSearchesUntilOneIsOnTheSite(): void
    {
        $this->repository()->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), slug: 'sobre-mi')));
        $this->entityManager()->clear();

        self::assertNull($this->repository()->findSearch());
    }

    public function testItFindsOnlyThePagesOnTheSiteAmongThoseGiven(): void
    {
        $first = PageIdMother::random();
        $second = PageIdMother::random();
        $pages = [
            Page::publish(CmsPagePublishedMother::create(id: $first->value(), title: 'Primera', slug: 'primera')),
            Page::publish(CmsPagePublishedMother::create(id: $second->value(), title: 'Segunda', slug: 'segunda')),
        ];
        $repository = $this->repository();
        $repository->save($pages[0]);
        $repository->save($pages[1]);
        $repository->save(Page::publish(CmsPagePublishedMother::create(id: PageIdMother::random()->value(), title: 'Otra', slug: 'otra')));
        $this->entityManager()->clear();

        self::assertEqualsCanonicalizing($pages, $repository->findByIds([$first, PageIdMother::random(), $second]));
        self::assertSame([], $repository->findByIds([]));
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

    /**
     * What the entity maps is what the migrations built.
     */
    public function testItsTableIsTheOneMapped(): void
    {
        self::assertTablesAreMapped('web_page');
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
