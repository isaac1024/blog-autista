<?php

declare(strict_types=1);

namespace Web\Redirect\Infrastructure\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Web\Redirect\Domain\Redirect;
use Web\Redirect\Domain\RedirectId;
use Web\Redirect\Domain\RedirectRepository;

/**
 * Outbound adapter that persists and reads the redirects of the site through
 * Doctrine ORM.
 */
#[AsAlias(RedirectRepository::class)]
final readonly class DoctrineRedirectRepository implements RedirectRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[\Override]
    public function save(Redirect $redirect): void
    {
        $this->entityManager->persist($redirect);
        $this->entityManager->flush();
    }

    #[\Override]
    public function delete(Redirect $redirect): void
    {
        $this->entityManager->remove($redirect);
        $this->entityManager->flush();
    }

    #[\Override]
    public function find(RedirectId $id): ?Redirect
    {
        return $this->entityManager->find(Redirect::class, $id);
    }

    #[\Override]
    public function findBySource(string $source): ?Redirect
    {
        return $this->entityManager->getRepository(Redirect::class)->findOneBy(['source' => $source], ['changedAt' => 'DESC', 'id' => 'DESC']);
    }
}
