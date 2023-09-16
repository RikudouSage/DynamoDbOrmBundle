<?php

namespace Rikudou\DynamoDbOrm\Service\EntityManager;

use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use Rikudou\DynamoDbOrm\Enum\SortOrder;

interface EntityManagerInterface
{
    /**
     * @param class-string<object> $entity
     *
     * @return array<string, AttributeValue>|null
     */
    public function find(string $entity, mixed $id): ?array;

    /**
     * @param class-string<object> $entity
     * @param array<string, mixed> $conditions
     *
     * @return iterable<array<string, AttributeValue>>
     */
    public function findBy(string $entity, array $conditions = [], SortOrder $order = SortOrder::Ascending): iterable;

    /**
     * @param class-string<object> $entity
     * @param array<string,mixed>  $conditions
     *
     * @return array<string, AttributeValue>|null
     */
    public function findOneBy(string $entity, array $conditions = []): ?array;

    /**
     * @param class-string<object> $entity
     *
     * @return iterable<array<string, AttributeValue>>
     */
    public function findAll(string $entity, SortOrder $order = SortOrder::Ascending): iterable;

    public function delete(object $entity): void;

    public function persist(object $entity): void;

    public function flush(): void;
}
