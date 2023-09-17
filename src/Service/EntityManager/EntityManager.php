<?php

namespace Rikudou\DynamoDbOrm\Service\EntityManager;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Exception\ResourceNotFoundException;
use DateTimeInterface;
use ReflectionException;
use ReflectionProperty;
use Rikudou\DynamoDbOrm\Enum\BeforeQuerySendEventType;
use Rikudou\DynamoDbOrm\Enum\ColumnType;
use Rikudou\DynamoDbOrm\Enum\SortOrder;
use Rikudou\DynamoDbOrm\Event\BeforeQuerySendEvent;
use Rikudou\DynamoDbOrm\Event\DynamoDbOrmEvents;
use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;
use Rikudou\DynamoDbOrm\Exception\UnsearchableColumnException;
use Rikudou\DynamoDbOrm\Service\EntityMetadata\EntityMetadataRegistry;
use Safe\Exceptions\JsonException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Safe\json_encode;

final class EntityManager implements EntityManagerInterface
{
    /**
     * @var object[]
     */
    private array $toPersist = [];

    /**
     * @var object[]
     */
    private array $toDelete = [];

    public function __construct(
        private readonly EntityMetadataRegistry $entityMetadataRegistry,
        private readonly DynamoDbClient $dynamoDbClient,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function find(string $entity, mixed $id): ?array
    {
        $metadata = $this->entityMetadataRegistry->getForEntity($entity);
        $primaryColumn = $metadata->getPrimaryColumn();

        $rawType = $primaryColumn->getType(false);
        if ($rawType === 'array' || $rawType === 'json') {
            $id = json_encode($id);
        }

        $requestArray = [
            'Key' => [
                $primaryColumn->name => [
                    $primaryColumn->getType() => $id,
                ],
            ],
            'TableName' => $metadata->getTable(),
        ];

        try {
            return $this->dynamoDbClient->getItem($requestArray)->getItem();
        } catch (ResourceNotFoundException) {
            return null;
        }
    }

    public function findBy(string $entity, array $conditions = [], SortOrder $order = SortOrder::Ascending): iterable
    {
        $metadata = $this->entityMetadataRegistry->getForEntity($entity);

        if (count($conditions) === 1 && array_key_first($conditions) === $metadata->getPrimaryColumn()->name) {
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
            if (!$column->searchable) {
                throw new UnsearchableColumnException("The column '{$columnName}' of entity '{$entity}' is not searchable");
            }

            if (!$column->primary) {
                foreach ($column->searchableIndexNames as $searchableIndexName) {
                    $indexes[$searchableIndexName][] = $column->name;
                }
            }

            if ($column->isManyToOne() && is_object($value)) {
                assert($column->manyToOneEntity !== null);
                $linkedMetadata = $this->entityMetadataRegistry->getForEntity($column->manyToOneEntity);
                $primaryKey = $linkedMetadata->getMappedName($linkedMetadata->getPrimaryColumn()->name);
                $getters = ['get' . ucfirst($primaryKey), 'is' . ucfirst($primaryKey)];
                $callable = null;
                foreach ($getters as $getter) {
                    if (method_exists($value, $getter)) {
                        $callable = [$value, $getter];
                        break;
                    }
                }
                if ($callable === null) {
                    $callable = static function () use ($primaryKey, $value) {
                        $reflection = new ReflectionProperty($value::class, $primaryKey);

                        return $reflection->getValue($value);
                    };
                }
                assert(is_callable($callable));
                $value = $callable();
            }

            $rawType = ColumnType::from($column->getType(false));
            if ($rawType === ColumnType::Array || $rawType === ColumnType::Json) {
                $value = json_encode($value);
            } elseif ($rawType === ColumnType::Number) {
                assert(is_scalar($value));
                $value = (string) $value;
            }

            $requestArray['KeyConditionExpression'] .= "{$column->name} = :value{$i} AND ";
            $requestArray['ExpressionAttributeValues'][":value{$i}"] = [$column->getType() => $value];
            ++$i;
        }

        $requestArray['KeyConditionExpression'] = substr($requestArray['KeyConditionExpression'], 0, -4);
        $requestArray['ScanIndexForward'] = $order === SortOrder::Ascending;

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

        $event = new BeforeQuerySendEvent($requestArray, BeforeQuerySendEventType::FindBy, $entity, $this->dynamoDbClient);
        $this->eventDispatcher->dispatch($event, DynamoDbOrmEvents::BEFORE_QUERY_SEND);
        $result = $event->getResult();

        if ($result !== null) {
            return $result; // @phpstan-ignore-line
        }

        $requestArray = $event->requestData;

        $result = $this->dynamoDbClient->query($requestArray); // @phpstan-ignore-line

        return $result->getItems();
    }

    public function findOneBy(string $entity, array $conditions = []): ?array
    {
        $metadata = $this->entityMetadataRegistry->getForEntity($entity);

        if (count($conditions) === 1 && array_key_first($conditions) === $metadata->getPrimaryColumn()->name) {
            return $this->find($entity, reset($conditions));
        }

        $items = [...$this->findBy($entity, $conditions)];
        if (count($items) === 0) {
            return null;
        }

        return $items[array_key_first($items)];
    }

    public function findAll(string $entity, SortOrder $order = SortOrder::Ascending): iterable
    {
        $metadata = $this->entityMetadataRegistry->getForEntity($entity);
        $requestArray = [
            'TableName' => $metadata->getTable(),
            'ScanIndexForward' => $order === SortOrder::Ascending,
        ];

        $event = new BeforeQuerySendEvent($requestArray, BeforeQuerySendEventType::FindAll, $entity, $this->dynamoDbClient);
        $this->eventDispatcher->dispatch($event, DynamoDbOrmEvents::BEFORE_QUERY_SEND);
        $result = $event->getResult();
        if ($result !== null) {
            return $result; // @phpstan-ignore-line
        }

        $requestArray = $event->requestData;
        $result = $this->dynamoDbClient->scan($requestArray); // @phpstan-ignore-line

        return $result->getItems();
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
                $result = $this->dynamoDbClient->batchWriteItem([ // @phpstan-ignore-line
                    'RequestItems' => $requestItems,
                ]);
                $requestItems = $result->getUnprocessedItems();
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
            $metadata = $this->entityMetadataRegistry->getForEntity($itemToPersist::class);
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
                    $reflection = new ReflectionProperty($itemToPersist::class, $columnName);
                    $value = $reflection->getValue($itemToPersist);
                } else {
                    $value = $getter();
                }

                if ($value === null && $generator = $definition->getGenerator()) {
                    $value = $generator->generateId();
                }

                if ($definition->isManyToOne() && is_object($value)) {
                    assert($definition->manyToOneEntity !== null);
                    $linkedMetadata = $this->entityMetadataRegistry->getForEntity($definition->manyToOneEntity);
                    $primaryKey = $linkedMetadata->getMappedName($linkedMetadata->getPrimaryColumn()->name);
                    $getters = ['get' . ucfirst($primaryKey), 'is' . ucfirst($primaryKey)];
                    $callable = null;
                    foreach ($getters as $getter) {
                        if (method_exists($value, $getter)) {
                            $callable = [$value, $getter];
                            break;
                        }
                    }
                    if ($callable === null) {
                        $callable = static function () use ($primaryKey, $value) {
                            $reflection = new ReflectionProperty($value::class, $primaryKey);

                            return $reflection->getValue($value);
                        };
                    }
                    assert(is_callable($callable));
                    $value = $callable();
                }

                $rawType = ColumnType::from($definition->getType(false));
                if ($rawType === ColumnType::Array || $rawType === ColumnType::Json) {
                    $value = json_encode($value);
                } elseif ($rawType === ColumnType::Number) {
                    $value = (string) $value;
                } elseif ($rawType === ColumnType::DateTime) {
                    assert($value instanceof DateTimeInterface);
                    $value = (string) $value->getTimestamp();
                }

                if ($value === null) {
                    continue;
                }

                $item[$definition->name] = [$definition->getType() => $value];
            }

            unset($this->toPersist[$key]);
            ++$count;
            ++$index[$metadata->getTable()];
        }

        foreach ($this->toDelete as $key => $itemToDelete) {
            if ($count === 25) {
                break;
            }
            $metadata = $this->entityMetadataRegistry->getForEntity($itemToDelete::class);
            if (!isset($result[$metadata->getTable()])) {
                $result[$metadata->getTable()] = [];
            }
            if (!isset($index[$metadata->getTable()])) {
                $index[$metadata->getTable()] = 0;
            }

            $value = null;
            foreach ($metadata->getColumns() as $columnName => $definition) {
                if (!$definition->primary) {
                    continue;
                }
                $getterNames = ['get' . ucfirst($columnName), 'is' . ucfirst($columnName)];
                foreach ($getterNames as $getterName) {
                    if (method_exists($itemToDelete, $getterName)) {
                        $callable = [$itemToDelete, $getterName];
                        assert(is_callable($callable));
                        $value = $callable();
                        break;
                    }
                }
                if ($value === null) {
                    $reflection = new ReflectionProperty($itemToDelete::class, $columnName);
                    $value = $reflection->getValue($itemToDelete);
                }
                break;
            }

            $result[$metadata->getTable()][$index[$metadata->getTable()]] = [
                'DeleteRequest' => [
                    'Key' => [
                        $metadata->getPrimaryColumn()->name => [
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
