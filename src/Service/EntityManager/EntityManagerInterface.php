<?php

namespace Rikudou\DynamoDbOrm\Service\EntityManager;

interface EntityManagerInterface
{
    /**
     * @param string $entity
     * @param mixed  $id
     *
     * @return array<string,array<string,mixed>>
     */
    public function find(string $entity, $id): ?array;

    /**
     * @param string              $entity
     * @param array<string,mixed> $conditions
     * @param string              $order
     *
     * @return array<string,array<string,mixed>>[]
     */
    public function findBy(string $entity, array $conditions = [], string $order = 'ASC'): array;

    /**
     * @param string              $entity
     * @param array<string,mixed> $conditions
     *
     * @return array<string,array<string,mixed>>
     */
    public function findOneBy(string $entity, array $conditions = []): ?array;

    /**
     * @param string $entity
     * @param string $order
     *
     * @return array<string,array<string,mixed>>[]
     */
    public function findAll(string $entity, string $order = 'ASC'): array;

    public function delete(object $entity): void;

    public function persist(object $entity): void;

    public function flush(): void;
}
