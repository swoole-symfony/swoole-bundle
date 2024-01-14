<?php

declare(strict_types=1);

namespace SwooleBundle\SwooleBundle\Bridge\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Repository\RepositoryFactory;
use Doctrine\Persistence\ObjectRepository;

final class ServicePooledRepositoryFactory implements RepositoryFactory
{
    public function __construct(
        private readonly RepositoryFactory $decorated,
        private readonly EntityManagerInterface $pooledEm
    ) {
    }

    public function getRepository(EntityManagerInterface $entityManager, $entityName): ObjectRepository
    {
        return $this->decorated->getRepository($this->pooledEm, $entityName);
    }
}
