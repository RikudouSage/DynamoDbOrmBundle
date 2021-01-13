<?php

namespace Rikudou\DynamoDbOrm\Service\EntityManager;

use Aws\DynamoDb\DynamoDbClient;
use ReflectionException;
use ReflectionProperty;
use Rikudou\DynamoDbOrm\Event\BeforeQuerySendEvent;
use Rikudou\DynamoDbOrm\Event\DynamoDbOrmEvents;
use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;
use Rikudou\DynamoDbOrm\Exception\UnsearchableColumnException;
use Rikudou\DynamoDbOrm\Service\EntityMetadata\EntityMetadataRegistry;
use Safe\Exceptions\JsonException;
use function Safe\json_encode;
use function Safe\substr;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EntityManager implements EntityManagerInterface
{
    /**
     * @var object[]
     */
    private $toPersist = [];

    /**
     * @var object[]
     */
    private $toDelete = [];

    /**
     * @var EntityMetadataRegistry
     */
    private $entityMetadataRegistry;

    /**
     * @var DynamoDbClient
     */
    private $dynamoDbClient;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityMetadataRegistry $entityMetadataRegistry,
        DynamoDbClient $dynamoDbClient,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->entityMetadataRegistry = $entityMetadataRegistry;
        $this->dynamoDbClient = $dynamoDbClient;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function find(string $entity, $id): ?array
    {
        $metadata = $this->entityMetadataRegistry->getForEntity($entity);
        $primaryColumn = $metadata->getPrimaryColumn();

        $rawType = $primaryColumn->getType(false);
        if ($rawType === 'array' || $rawType === 'json') {
            $id = json_encode($id);
        }

        $requestArray = [
            'Key' => [
                $primaryColumn->getName() => [
                    $primaryColumn->getType() => $id,
                ],
            ],
            'TableName' => $metadata->getTable(),
        ];

        return $this->dynamoDbClient->getItem($requestArray)->get('Item');
    }

    public function findBy(string $entity, array $conditions = [], string $order = 'ASC'): array
    {
        $metadata = $this->entityMetadataRegistry->getForEntity($entity);

        if (count($conditions) === 1 && array_key_first($conditions) === $metadata->getPrimaryColumn()->getName()) {
            $result = $this->find($entity, reset($conditions));
            if ($result === null) {
                return [];
            }

            return [$result];
        }

        $requestArray = [
            'TableName' => $metadata->getTable(),
            'KeyConditionExpression' => '',
            'ExpressionAttributeValues' => [],
        ];

        $indexes = [];

        $i = 0;
        foreach ($conditions as $columnName => $value) {
            $column = $metadata->getColumn($columnName);
            if (!$column->isSearchable()) {
                throw new UnsearchableColumnException("The column '{$columnName}' of entity '{$entity}' is not searchable");
            }

            if (!$column->isPrimary()) {
                foreach ($column->getSearchableIndexNames() as $searchableIndexName) {
                    $indexes[$searchableIndexName][] = $column->getName();
                }
            }

            if ($column->isManyToOne() && is_object($value)) {
                assert($column->getManyToOneEntity() !== null);
                $linkedMetadata = $this->entityMetadataRegistry->getForEntity($column->getManyToOneEntity());
                $primaryKey = $linkedMetadata->getMappedName($linkedMetadata->getPrimaryColumn()->getName());
                $getters = ['get' . ucfirst($primaryKey), 'is' . ucfirst($primaryKey)];
                $callable = null;
                foreach ($getters as $getter) {
                    if (method_exists($value, $getter)) {
                        $callable = [$value, $getter];
                        break;
                    }
                }
                if ($callable === null) {
                    $callable = function () use ($primaryKey, $value) {
                        $reflection = new ReflectionProperty(get_class($value), $primaryKey);
                        $reflection->setAccessible(true);

                        return $reflection->getValue($value);
                    };
                }
                assert(is_callable($callable));
                $value = call_user_func($callable);
            }

            $rawType = $column->getType(false);
            if ($rawType === 'array' || $rawType === 'json') {
                $value = json_encode($value);
            } elseif ($rawType === 'number') {
                $value = (string) $value;
            }

            $requestArray['KeyConditionExpression'] .= "{$column->getName()} = :value{$i} AND ";
            $requestArray['ExpressionAttributeValues'][":value{$i}"] = [$column->getType() => $value];
            ++$i;
        }

        $requestArray['KeyConditionExpression'] = substr($requestArray['KeyConditionExpression'], 0, -4);
        $requestArray['ScanIndexForward'] = strcasecmp($order, 'ASC') === 0;

        if (count($indexes) !== 0) {
            $found = false;
            foreach ($indexes as $index => $columns) {
                if (count($columns) === count($conditions)) {
                    $requestArray['IndexName'] = $index;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new UnsearchableColumnException(
                    'Could not find any common index for specified fields: ' . implode(', ', array_keys($conditions))
                );
            }
        }

        $items = [];

        $event = new BeforeQuerySendEvent($requestArray, BeforeQuerySendEvent::TYPE_FIND_BY, $entity, $this->dynamoDbClient);
        $this->eventDispatcher->dispatch($event, DynamoDbOrmEvents::BEFORE_QUERY_SEND);
        $result = $event->getResult();

        if ($result !== null) {
            return $result;
        }

        $requestArray = $event->getRequestData();

        do {
            $result = $this->dynamoDbClient->query($requestArray);
            $items = array_merge($items, $result->get('Items'));
            $requestArray['ExclusiveStartKey'] = $result->get('LastEvaluatedKey');
        } while ($result->get('LastEvaluatedKey'));

        return $items;
    }

    public function findOneBy(string $entity, array $conditions = []): ?array
    {
        $metadata = $this->entityMetadataRegistry->getForEntity($entity);

        if (count($conditions) === 1 && array_key_first($conditions) === $metadata->getPrimaryColumn()->getName()) {
            return $this->find($entity, reset($conditions));
        }

        $items = $this->findBy($entity, $conditions);
        if (count($items) === 0) {
            return null;
        }

        return reset($items);
    }

    public function findAll(string $entity): array
    {
        $metadata = $this->entityMetadataRegistry->getForEntity($entity);
        $requestArray = [
            'TableName' => $metadata->getTable(),
        ];

        $items = [];

        do {
            $result = $this->dynamoDbClient->scan($requestArray);
            $items = array_merge($items, $result->get('Items'));
            $requestArray['ExclusiveStartKey'] = $result->get('LastEvaluatedKey');
        } while ($result->get('LastEvaluatedKey'));

        return $items;
    }

    public function delete(object $entity): void
    {
        $this->toDelete[] = $entity;
    }

    public function persist(object $entity): void
    {
        $this->toPersist[] = $entity;
    }

    public function flush(): void
    {
        do {
            $fullRequestItems = $this->getRequestItems();
            $requestItems = $fullRequestItems;
            while ($requestItems) {
                $result = $this->dynamoDbClient->batchWriteItem([
                    'RequestItems' => $requestItems,
                ]);
                $requestItems = $result->get('UnprocessedItems');
            }
        } while ($fullRequestItems);
    }

    /**
     * @throws ReflectionException
     * @throws EntityNotFoundException
     * @throws JsonException
     *
     * @return mixed[]
     */
    private function getRequestItems(): array
    {
        $result = [];
        $index = [];
        $count = 0;

        foreach ($this->toPersist as $key => $itemToPersist) {
            if ($count === 25) {
                break;
            }
            $metadata = $this->entityMetadataRegistry->getForEntity(get_class($itemToPersist));
            if (!isset($result[$metadata->getTable()])) {
                $result[$metadata->getTable()] = [];
            }
            if (!isset($index[$metadata->getTable()])) {
                $index[$metadata->getTable()] = 0;
            }
            $result[$metadata->getTable()][$index[$metadata->getTable()]] = [
                'PutRequest' => [
                    'Item' => [],
                ],
            ];
            $item = &$result[$metadata->getTable()][$index[$metadata->getTable()]]['PutRequest']['Item'];

            foreach ($metadata->getColumns() as $columnName => $definition) {
                if ($definition->isOneToMany()) {
                    continue;
                }
                $getterNames = ['get' . ucfirst($columnName), 'is' . ucfirst($columnName)];
                $getter = null;
                foreach ($getterNames as $getterName) {
                    if (method_exists($itemToPersist, $getterName)) {
                        $getter = [$itemToPersist, $getterName];
                        break;
                    }
                }

                if (!is_callable($getter)) {
                    $reflection = new ReflectionProperty(get_class($itemToPersist), $columnName);
                    $reflection->setAccessible(true);
                    $value = $reflection->getValue($itemToPersist);
                } else {
                    $value = call_user_func($getter);
                }

                if ($value === null && $generator = $definition->getGenerator()) {
                    $value = $generator->generateId();
                }

                if ($definition->isManyToOne() && is_object($value)) {
                    assert($definition->getManyToOneEntity() !== null);
                    $linkedMetadata = $this->entityMetadataRegistry->getForEntity($definition->getManyToOneEntity());
                    $primaryKey = $linkedMetadata->getMappedName($linkedMetadata->getPrimaryColumn()->getName());
                    $getters = ['get' . ucfirst($primaryKey), 'is' . ucfirst($primaryKey)];
                    $callable = null;
                    foreach ($getters as $getter) {
                        if (method_exists($value, $getter)) {
                            $callable = [$value, $getter];
                            break;
                        }
                    }
                    if ($callable === null) {
                        $callable = function () use ($primaryKey, $value) {
                            $reflection = new ReflectionProperty(get_class($value), $primaryKey);
                            $reflection->setAccessible(true);

                            return $reflection->getValue($value);
                        };
                    }
                    assert(is_callable($callable));
                    $value = call_user_func($callable);
                }

                $rawType = $definition->getType(false);
                if ($rawType === 'array' || $rawType === 'json') {
                    $value = json_encode($value);
                } elseif ($rawType === 'number') {
                    $value = (string) $value;
                }

                if ($value === null) {
                    continue;
                }

                $item[$definition->getName()] = [$definition->getType() => $value];
            }

            unset($this->toPersist[$key]);
            ++$count;
            ++$index[$metadata->getTable()];
        }

        foreach ($this->toDelete as $key => $itemToDelete) {
            if ($count === 25) {
                break;
            }
            $metadata = $this->entityMetadataRegistry->getForEntity(get_class($itemToDelete));
            if (!isset($result[$metadata->getTable()])) {
                $result[$metadata->getTable()] = [];
            }
            if (!isset($index[$metadata->getTable()])) {
                $index[$metadata->getTable()] = 0;
            }

            $value = null;
            foreach ($metadata->getColumns() as $columnName => $definition) {
                if (!$definition->isPrimary()) {
                    continue;
                }
                $getterNames = ['get' . ucfirst($columnName), 'is' . ucfirst($columnName)];
                foreach ($getterNames as $getterName) {
                    if (method_exists($itemToDelete, $getterName)) {
                        $callable = [$itemToDelete, $getterName];
                        assert(is_callable($callable));
                        $value = call_user_func($callable);
                        break;
                    }
                }
                if ($value === null) {
                    $reflection = new ReflectionProperty(get_class($itemToDelete), $columnName);
                    $reflection->setAccessible(true);
                    $value = $reflection->getValue($itemToDelete);
                }
                break;
            }

            $result[$metadata->getTable()][$index[$metadata->getTable()]] = [
                'DeleteRequest' => [
                    'Key' => [
                        $metadata->getPrimaryColumn()->getName() => [
                            $metadata->getPrimaryColumn()->getType() => $value,
                        ],
                    ],
                ],
            ];

            unset($this->toDelete[$key]);
            ++$index[$metadata->getTable()];
            ++$count;
        }

        return $result;
    }
}
