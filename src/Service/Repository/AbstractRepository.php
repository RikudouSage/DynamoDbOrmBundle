<?php

namespace Rikudou\DynamoDbOrm\Service\Repository;

use Rikudou\DynamoDbOrm\Service\EntityManager\EntityManagerInterface;
use Rikudou\DynamoDbOrm\Service\EntityMapper;
use Rikudou\DynamoDbOrm\Service\EntityMetadata\EntityMetadataRegistry;

abstract class AbstractRepository implements RepositoryInterface
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var EntityMetadataRegistry
     */
    private $entityMetadataRegistry;

    /**
     * @var EntityMapper
     */
    private $entityMapper;

    public function find($id): ?object
    {
        $result = $this->entityManager->find($this->getEntityClass(), $id);
        if ($result === null) {
            return null;
        }

        return $this->entityMapper->map($this->getEntityClass(), $result);
    }

    public function findBy(array $conditions = [], string $order = 'ASC'): array
    {
        return $this->entityMapper->mapMultiple(
            $this->getEntityClass(),
            $this->entityManager->findBy($this->getEntityClass(), $conditions, $order)
        );
    }

    public function findOneBy(array $conditions = []): ?object
    {
        $result = $this->entityManager->findOneBy($this->getEntityClass(), $conditions);
        if ($result === null) {
            return null;
        }

        return $this->entityMapper->map($this->getEntityClass(), $result);
    }

    public function findAll(string $order = 'ASC'): array
    {
        return $this->entityMapper->mapMultiple(
            $this->getEntityClass(),
            $this->entityManager->findAll($this->getEntityClass(), $order)
        );
    }

    public function setDependencies(
        EntityManagerInterface $entityManager,
        EntityMetadataRegistry $entityMetadataRegistry,
        EntityMapper $entityMapper
    ): void {
        $this->entityManager = $entityManager;
        $this->entityMetadataRegistry = $entityMetadataRegistry;
        $this->entityMapper = $entityMapper;
    }
}
