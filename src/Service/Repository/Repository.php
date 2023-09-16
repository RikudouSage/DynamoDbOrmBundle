<?php

namespace Rikudou\DynamoDbOrm\Service\Repository;

use Rikudou\DynamoDbOrm\Enum\SortOrder;

/**
 * @template TEntity of object
 */
interface Repository
{
    /**
     * @return TEntity|null
     */
    public function find(int|string $id): ?object;

    /**
     * @param array<string, mixed> $conditions
     *
     * @return TEntity[]
     */
    public function findBy(array $conditions = [], SortOrder $order = SortOrder::Ascending): array;

    /**
     * @param array<string, mixed> $conditions
     *
     * @return TEntity|null
     */
    public function findOneBy(array $conditions = []): ?object;

    /**
     * @return TEntity[]
     */
    public function findAll(SortOrder $order = SortOrder::Ascending): array;

    /**
     * @return class-string<TEntity>
     */
    public function getEntityClass(): string;
}
