<?php

declare(strict_types=1);

namespace Cms\Tests\Page\Infrastructure\Persistence;

use Cms\Page\Domain\Page;
use Cms\Page\Domain\PageId;
use Cms\Page\Domain\PageRepository;
use Cms\Tests\Page\Domain\PageMother;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePageRepositoryTest extends KernelTestCase
{
    public function testItSavesThePageSoItCanBeReadBack(): void
    {
        $id = PageId::random();
        $page = PageMother::create(id: $id->value(), title: 'Sobre mí', slug: 'sobre-mi');
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        self::getContainer()->get(PageRepository::class)->save($page);
        $entityManager->clear();

        self::assertEquals($page, $entityManager->find(Page::class, $id));
    }
}
