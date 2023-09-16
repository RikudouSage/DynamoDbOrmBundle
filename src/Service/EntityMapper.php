<?php

namespace Rikudou\DynamoDbOrm\Service;

use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Rikudou\DynamoDbOrm\Enum\ColumnType;
use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;
use Rikudou\DynamoDbOrm\Service\EntityMetadata\EntityMetadataRegistry;
use Rikudou\DynamoDbOrm\Service\Repository\RepositoryRegistryInterface;
use Safe\DateTimeImmutable;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Inflector\InflectorInterface;

use function Safe\json_decode;

final class EntityMapper
{
    /**
     * @var array<string, array<string|int, object>>
     */
    private array $mapped = [];

    private readonly InflectorInterface $inflector;

    public function __construct(
        private readonly EntityMetadataRegistry $entityMetadataRegistry,
        private readonly RepositoryRegistryInterface $repositoryRegistry
    ) {
        $this->inflector = new EnglishInflector();
    }

    /**
     * @template TEntity of object
     *
     * @param class-string<TEntity>         $entityClass
     * @param array<string, AttributeValue> $data
     *
     * @throws ReflectionException
     * @throws EntityNotFoundException
     *
     * @return TEntity
     */
    public function map(string $entityClass, array $data): object
    {
        $data = $this->attributeMapToArray($data);

        $reflection = new ReflectionClass($entityClass);
        $entity = $reflection->newInstanceWithoutConstructor();
        $metadata = $this->entityMetadataRegistry->getForEntity($entityClass);

        $primaryKey = $metadata->getPrimaryColumn();
        $primaryKeyValue = $data[$primaryKey->name][$primaryKey->getType()];
        if (isset($this->mapped[$entityClass][$primaryKeyValue])) {
            return $this->mapped[$entityClass][$primaryKeyValue]; // @phpstan-ignore-line
        }

        $this->mapped[$entityClass][$primaryKeyValue] = $entity;

        foreach ($data as $columnName => $valueData) {
            $columnName = $metadata->getMappedName($columnName);
            $columnDefinition = $metadata->getColumn($columnName);
            $rawType = ColumnType::from($columnDefinition->getType(false));
            $value = $valueData[$columnDefinition->getType()];

            if ($columnDefinition->isManyToOne()) {
                if (isset($this->mapped[$columnDefinition->manyToOneEntity][$value])) {
                    $value = $this->mapped[$columnDefinition->manyToOneEntity][$value];
                } else {
                    assert($columnDefinition->manyToOneEntity !== null);
                    $repository = $this->repositoryRegistry->getRepository($columnDefinition->manyToOneEntity);
                    assert(is_string($value) || is_int($value));
                    $value = $repository->find($value);
                }
            } elseif ($rawType === ColumnType::Array || $rawType === ColumnType::Json) {
                assert(is_string($value));
                $value = json_decode($value, true);
            } elseif ($rawType === ColumnType::Number) {
                assert(is_string($value));
                if ((string) ((int) $value) !== $value) {
                    $value = (float) $value;
                } else {
                    $value = (int) $value;
                }
            } elseif ($rawType === ColumnType::DateTime) {
                assert(is_string($value));
                $value = (new DateTimeImmutable())->setTimestamp((int) $value);
            }

            $setter = 'set' . ucfirst($columnName);
            if (!method_exists($entity, $setter)) {
                $propertyReflection = $reflection->getProperty($columnName);
                $propertyReflection->setValue($entity, $value);
            } else {
                $callable = [$entity, $setter];
                assert(is_callable($callable));
                $callable($value);
            }
        }

        foreach ($metadata->getColumns() as $columnName => $column) {
            if ($column->isOneToMany()) {
                assert($column->oneToManyEntity !== null);
                $repository = $this->repositoryRegistry->getRepository($column->oneToManyEntity);
                $adders = ['add' . ucfirst($this->inflector->singularize($columnName)[0])];

                $callable = null;
                foreach ($adders as $adder) {
                    if (method_exists($entity, $adder)) {
                        $callable = [$entity, $adder];
                        break;
                    }
                }
                if ($callable === null) {
                    $callable = static function ($value) use ($columnName, $entity): void {
                        $reflection = new ReflectionProperty($entity::class, $columnName);
                        $originalValue = $reflection->getValue($entity);
                        if (!is_array($originalValue)) {
                            $originalValue = [];
                        }
                        $originalValue[] = $value;
                        $reflection->setValue($entity, $value);
                    };
                }
                $linkedEntities = $repository->findBy([
                    $column->oneToManyField => $primaryKeyValue,
                ]);

                assert(is_callable($callable));
                foreach ($linkedEntities as $linkedEntity) {
                    $callable($linkedEntity);
                }
            }
        }

        $this->mapped[$entityClass][$primaryKeyValue] = $entity;

        return $entity;
    }

    /**
     * @template TEntity of object
     *
     * @param class-string<TEntity>                   $entityClass
     * @param iterable<array<string, AttributeValue>> $items
     *
     * @throws EntityNotFoundException
     * @throws ReflectionException
     *
     * @return TEntity[]
     */
    public function mapMultiple(string $entityClass, iterable $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $result[] = $this->map($entityClass, $item);
        }

        return $result;
    }

    /**
     * @param array<string, AttributeValue> $data
     *
     * @return array<string, array<string, mixed>>
     */
    private function attributeMapToArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = $this->attributeValueToArray($value);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributeValueToArray(AttributeValue $value): array
    {
        return [
            'S' => $value->getS(),
            'N' => $value->getN(),
            'B' => $value->getB(),
            'SS' => $value->getSs(),
            'NS' => $value->getNs(),
            'BS' => $value->getBs(),
            'NULL' => $value->getNull(),
            'BOOl' => $value->getBool(),
            'L' => array_map($this->attributeValueToArray(...), $value->getL()),
            'M' => $this->attributeMapToArray($value->getM()),
        ];
    }
}
