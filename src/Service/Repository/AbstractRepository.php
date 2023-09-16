<?php

namespace Rikudou\DynamoDbOrm\Service\Repository;

use ReflectionException;
use Rikudou\DynamoDbOrm\Enum\SortOrder;
use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;
use Rikudou\DynamoDbOrm\Service\EntityManager\EntityManagerInterface;
use Rikudou\DynamoDbOrm\Service\EntityMapper;
use Rikudou\DynamoDbOrm\Service\EntityMetadata\EntityMetadataRegistry;

/**
 * @template TEntity of object
 *
 * @implements Repository<TEntity>
 */
abstract class AbstractRepository implements Repository
{
    private EntityManagerInterface $entityManager;

    protected EntityMetadataRegistry $entityMetadataRegistry;

    private EntityMapper $entityMapper;

    /**
     * @throws ReflectionException
     * @throws EntityNotFoundException
     *
     * @return TEntity|null
     */
    public function find(int|string $id): ?object
    {
        $result = $this->entityManager->find($this->getEntityClass(), $id);
        if ($result === null) {
            return null;
        }

        return $this->entityMapper->map($this->getEntityClass(), $result);
    }

    /**
     * @param array<string, mixed> $conditions
     *
     * @throws EntityNotFoundException
     * @throws ReflectionException
     *
     * @return array<TEntity>
     */
    public function findBy(array $conditions = [], SortOrder $order = SortOrder::Ascending): array
    {
        return $this->entityMapper->mapMultiple(
            $this->getEntityClass(),
            $this->entityManager->findBy($this->getEntityClass(), $conditions, $order)
        );
    }

    /**
     * @param array<string, mixed> $conditions
     *
     * @throws EntityNotFoundException
     * @throws ReflectionException
     *
     * @return TEntity|null
     */
    public function findOneBy(array $conditions = []): ?object
    {
        $result = $this->entityManager->findOneBy($this->getEntityClass(), $conditions);
        if ($result === null) {
            return null;
        }

        return $this->entityMapper->map($this->getEntityClass(), $result);
    }

    /**
     * @throws EntityNotFoundException
     * @throws ReflectionException
     *
     * @return TEntity[]
     */
    public function findAll(SortOrder $order = SortOrder::Ascending): array
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
