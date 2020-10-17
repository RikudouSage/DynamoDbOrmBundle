<?php

namespace Rikudou\DynamoDbOrm\Service;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;
use Rikudou\DynamoDbOrm\Service\EntityMetadata\EntityMetadataRegistry;
use Rikudou\DynamoDbOrm\Service\Repository\RepositoryRegistryInterface;
use function Safe\json_decode;
use Symfony\Component\String\Inflector\EnglishInflector;
use Symfony\Component\String\Inflector\InflectorInterface;

final class EntityMapper
{
    /**
     * @var array<string,array<string|int,object>>
     */
    private $mapped = [];

    /**
     * @var EntityMetadataRegistry
     */
    private $entityMetadataRegistry;

    /**
     * @var RepositoryRegistryInterface
     */
    private $repositoryRegistry;

    /**
     * @var InflectorInterface
     */
    private $inflector;

    public function __construct(
        EntityMetadataRegistry $entityMetadataRegistry,
        RepositoryRegistryInterface $repositoryRegistry
    ) {
        $this->entityMetadataRegistry = $entityMetadataRegistry;
        $this->repositoryRegistry = $repositoryRegistry;
        $this->inflector = new EnglishInflector();
    }

    /**
     * @param string                            $entityClass
     * @param array<string,array<string,mixed>> $data
     *
     * @throws ReflectionException
     * @throws EntityNotFoundException
     *
     * @return object
     */
    public function map(string $entityClass, array $data): object
    {
        $reflection = new ReflectionClass($entityClass);
        $entity = $reflection->newInstanceWithoutConstructor();
        $metadata = $this->entityMetadataRegistry->getForEntity($entityClass);

        $primaryKey = $metadata->getPrimaryColumn();
        $primaryKeyValue = $data[$primaryKey->getName()][$primaryKey->getType()];
        if (isset($this->mapped[$entityClass][$primaryKeyValue])) {
            return $this->mapped[$entityClass][$primaryKeyValue];
        }

        $this->mapped[$entityClass][$primaryKeyValue] = $entity;

        foreach ($data as $columnName => $valueData) {
            $columnName = $metadata->getMappedName($columnName);
            $columnDefinition = $metadata->getColumn($columnName);
            $rawType = $columnDefinition->getType(false);
            $value = $valueData[$columnDefinition->getType()];

            if ($columnDefinition->isManyToOne()) {
                if (isset($this->mapped[$columnDefinition->getManyToOneEntity()][$value])) {
                    $value = $this->mapped[$columnDefinition->getManyToOneEntity()][$value];
                } else {
                    assert($columnDefinition->getManyToOneEntity() !== null);
                    $repository = $this->repositoryRegistry->getRepository($columnDefinition->getManyToOneEntity());
                    $value = $repository->find($value);
                }
            } elseif ($rawType === 'array' || $rawType === 'json') {
                $value = json_decode($value, true);
            } elseif ($rawType === 'number') {
                if (strval(intval($value)) !== $value) {
                    $value = (float) $value;
                } else {
                    $value = (int) $value;
                }
            }

            $setter = 'set' . ucfirst($columnName);
            if (!method_exists($entity, $setter)) {
                $propertyReflection = $reflection->getProperty($columnName);
                $propertyReflection->setAccessible(true);
                $propertyReflection->setValue($entity, $value);
            } else {
                $callable = [$entity, $setter];
                assert(is_callable($callable));
                call_user_func($callable, $value);
            }
        }

        foreach ($metadata->getColumns() as $columnName => $column) {
            if ($column->isOneToMany()) {
                assert($column->getOneToManyEntity() !== null);
                $repository = $this->repositoryRegistry->getRepository($column->getOneToManyEntity());
                $adders = ['add' . ucfirst($this->inflector->singularize($columnName)[0])];

                $callable = null;
                foreach ($adders as $adder) {
                    if (method_exists($entity, $adder)) {
                        $callable = [$entity, $adder];
                        break;
                    }
                }
                if ($callable === null) {
                    $callable = function ($value) use ($columnName, $entity) {
                        $reflection = new ReflectionProperty(get_class($entity), $columnName);
                        $reflection->setAccessible(true);
                        $originalValue = $reflection->getValue($entity);
                        if (!is_array($originalValue)) {
                            $originalValue = [];
                        }
                        $originalValue[] = $value;
                        $reflection->setValue($entity, $value);
                    };
                }
                $linkedEntities = $repository->findBy([
                    $column->getOneToManyField() => $primaryKeyValue,
                ]);

                assert(is_callable($callable));
                foreach ($linkedEntities as $linkedEntity) {
                    call_user_func($callable, $linkedEntity);
                }
            }
        }

        $this->mapped[$entityClass][$primaryKeyValue] = $entity;

        return $entity;
    }

    /**
     * @param string                              $entityClass
     * @param array<string,array<string,mixed>>[] $items
     *
     * @throws EntityNotFoundException
     * @throws ReflectionException
     *
     * @return object[]
     */
    public function mapMultiple(string $entityClass, array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            $result[] = $this->map($entityClass, $item);
        }

        return $result;
    }
}
