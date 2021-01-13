<?php

namespace Rikudou\DynamoDbOrm\Service\Repository;

interface RepositoryInterface
{
    /**
     * @param string|int $id
     *
     * @return object
     */
    public function find($id): ?object;

    /**
     * @param array<string,mixed> $conditions
     * @param string              $order
     *
     * @return object[]
     */
    public function findBy(array $conditions = [], string $order = 'ASC'): array;

    /**
     * @param array<string,mixed> $conditions
     *
     * @return object
     */
    public function findOneBy(array $conditions = []): ?object;

    /**
     * @param string $order
     *
     * @return object[]
     */
    public function findAll(string $order = 'ASC'): array;

    public function getEntityClass(): string;
}
