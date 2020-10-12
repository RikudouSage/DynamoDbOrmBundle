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
     *
     * @return object[]
     */
    public function findBy(array $conditions = []): array;

    /**
     * @param array<string,mixed> $conditions
     *
     * @return object
     */
    public function findOneBy(array $conditions = []): ?object;

    /**
     * @return object[]
     */
    public function findAll(): array;

    public function getEntityClass(): string;
}
