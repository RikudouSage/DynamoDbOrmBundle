<?php

namespace Rikudou\DynamoDbOrm\Service\EntityMetadata;

use LogicException;
use ReflectionClass;
use ReflectionException;
use Rikudou\DynamoDbOrm\Annotation\Column;
use Rikudou\DynamoDbOrm\Annotation\Entity;
use Rikudou\DynamoDbOrm\Annotation\GeneratedId;
use Rikudou\DynamoDbOrm\Annotation\ManyToOne;
use Rikudou\DynamoDbOrm\Annotation\OneToMany;
use Rikudou\DynamoDbOrm\Annotation\PrimaryKey;
use Rikudou\DynamoDbOrm\Annotation\SearchableColumn;
use Rikudou\DynamoDbOrm\Enum\ColumnType;
use Rikudou\DynamoDbOrm\Enum\GeneratedIdType;
use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;
use Rikudou\DynamoDbOrm\Exception\InvalidEntityException;
use Rikudou\DynamoDbOrm\Exception\UnknownColumnException;
use Rikudou\DynamoDbOrm\Service\AttributeReader;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGeneratorRegistry;
use Rikudou\DynamoDbOrm\Service\NameConverter\NameConverter;
use Rikudou\DynamoDbOrm\Service\TableNameConverter;
use Rikudou\DynamoDbOrm\Service\TypeConverter;

final class EntityClassMetadata
{
    private string $table;

    private string $primaryKeyIndex;

    /**
     * @var array<string, array{name: string, primary: bool, type: ColumnType, searchable: bool, searchableIndexNames: array<string>, generator: class-string|GeneratedIdType|null, generatorParameters: array<mixed>, manyToOneEntity: class-string|null, oneToManyEntity: class-string|null, oneToManyField: string|null}>
     */
    private array $columns = [];

    /**
     * @param class-string          $class
     * @param array<string, string> $tableMapping
     *
     * @throws EntityNotFoundException
     */
    public function __construct(
        public string $class,
        private readonly array $tableMapping,
        private readonly NameConverter $nameConverter,
        private readonly IdGeneratorRegistry $idGeneratorRegistry,
        private readonly TypeConverter $typeConverter,
        private readonly TableNameConverter $tableNameConverter,
        private readonly AttributeReader $attributeReader,
    ) {
        $this->parse();
    }

    /**
     * @return ColumnDefinition[]
     */
    public function getColumns(): array
    {
        $result = [];
        foreach ($this->columns as $columnName => $column) {
            $result[$columnName] = $this->getColumn($columnName);
        }

        return $result;
    }

    public function getColumn(string $name): ColumnDefinition
    {
        if (!isset($this->columns[$name])) {
            throw new UnknownColumnException("The column '{$name}' not found on entity '{$this->class}'");
        }

        $column = $this->columns[$name];

        return new ColumnDefinition(
            $column['name'],
            $column['primary'],
            $column['type'],
            $column['searchable'],
            $column['searchableIndexNames'],
            $column['generator'],
            $column['generatorParameters'],
            $column['manyToOneEntity'],
            $column['oneToManyEntity'],
            $column['oneToManyField'],
            $this->idGeneratorRegistry,
            $this->typeConverter
        );
    }

    public function getMappedName(string $name): string
    {
        foreach ($this->columns as $columnName => $column) {
            if ($column['name'] === $name) {
                return $columnName;
            }
        }

        throw new UnknownColumnException("Could not find column that is mapped to '{$name}' on entity '{$this->class}'");
    }

    public function getPrimaryColumn(): ColumnDefinition
    {
        return $this->getColumn($this->columns[$this->primaryKeyIndex]['name']);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    private function parse(): void
    {
        try {
            $classReflection = new ReflectionClass($this->class);
            $entityAttribute = $this->attributeReader->getClassAnnotation($classReflection, Entity::class);
            if (!$entityAttribute instanceof Entity) {
                throw new InvalidEntityException("The entity '{$this->class}' must contain @Entity annotation");
            }
            if (isset($this->tableMapping[$this->class])) {
                $this->table = $this->tableMapping[$this->class];
            } else {
                if (!$entityAttribute->table) {
                    throw new LogicException('You must set the table name in annotation or table mapping in config');
                }
                $this->table = $this->tableNameConverter->getName($entityAttribute->table);
            }

            $hasPrimaryKey = false;
            foreach ($classReflection->getProperties() as $propertyReflection) {
                $columnDefinition = [
                    'name' => '',
                    'primary' => false,
                    'type' => ColumnType::String,
                    'searchable' => false,
                    'searchableIndexNames' => [],
                    'generator' => null,
                    'generatorParameters' => [],
                    'manyToOneEntity' => null,
                    'oneToManyEntity' => null,
                    'oneToManyField' => null,
                ];

                $columnAnnotation = $this->attributeReader->getPropertyAnnotation($propertyReflection, Column::class);
                $oneToManyAnnotation = $this->attributeReader->getPropertyAnnotation($propertyReflection, OneToMany::class);
                $manyToOneAnnotation = $this->attributeReader->getPropertyAnnotation($propertyReflection, ManyToOne::class);
                if ($columnAnnotation instanceof Column) {
                    if ($columnAnnotation->name === null) {
                        $columnAnnotation->name = $this->nameConverter->convertForDynamoDb($propertyReflection->getName());
                    }
                    $columnDefinition['name'] = $columnAnnotation->name;
                    $columnDefinition['type'] = $columnAnnotation->type;

                    $primaryKeyAnnotation = $this->attributeReader->getPropertyAnnotation($propertyReflection, PrimaryKey::class);
                    if ($primaryKeyAnnotation !== null) {
                        if ($hasPrimaryKey) {
                            throw new InvalidEntityException("Entity '{$this->class}' cannot have more than one primary key");
                        }
                        $this->primaryKeyIndex = $propertyReflection->getName();
                        $hasPrimaryKey = true;
                        $columnDefinition['primary'] = true;
                        $columnDefinition['searchable'] = true;
                    }

                    $generatorAnnotation = $this->attributeReader->getPropertyAnnotation($propertyReflection, GeneratedId::class);
                    if ($generatorAnnotation instanceof GeneratedId) {
                        if ($generatorAnnotation->type === GeneratedIdType::Custom && $generatorAnnotation->customGenerator === null) {
                            throw new InvalidEntityException("The generator for '{$propertyReflection->getName()}' of entity '{$this->class}' is set to custom but no custom generator is set");
                        }
                        if ($generatorAnnotation->type === GeneratedIdType::RandomString) {
                            if ($generatorAnnotation->randomStringLength <= 0 || $generatorAnnotation->randomStringLength % 2 !== 0) {
                                throw new InvalidEntityException("The randomString generator for '{$propertyReflection->getName()}' of entity '{$this->class}' must be greater than zero and divisible by 2");
                            }
                            $columnDefinition['generatorParameters'][] = $generatorAnnotation->randomStringLength;
                        }

                        $columnDefinition['generator'] = $generatorAnnotation->type === GeneratedIdType::Custom
                            ? $generatorAnnotation->customGenerator
                            : $generatorAnnotation->type;
                    }

                    $searchableAnnotations = $this->attributeReader->getPropertyAnnotations($propertyReflection);
                    foreach ($searchableAnnotations as $searchableAnnotation) {
                        if ($searchableAnnotation instanceof SearchableColumn) {
                            if (!$searchableAnnotation->indexName) {
                                $searchableAnnotation->indexName = 'idx-' . $columnAnnotation->name;
                            }
                            $columnDefinition['searchable'] = true;
                            $columnDefinition['searchableIndexNames'][] = $searchableAnnotation->indexName;
                        }
                    }
                } elseif ($manyToOneAnnotation instanceof ManyToOne) {
                    if (!$manyToOneAnnotation->joinColumn) {
                        $manyToOneAnnotation->joinColumn = $this->nameConverter->convertForDynamoDb($propertyReflection->getName()) . '_id';
                    }
                    if (!$manyToOneAnnotation->indexName) {
                        $manyToOneAnnotation->indexName = 'idx-' . $manyToOneAnnotation->joinColumn;
                    }
                    $columnDefinition['name'] = $manyToOneAnnotation->joinColumn;
                    $parser = new self(
                        $manyToOneAnnotation->entity,
                        $this->tableMapping,
                        $this->nameConverter,
                        $this->idGeneratorRegistry,
                        $this->typeConverter,
                        $this->tableNameConverter,
                        $this->attributeReader,
                    );
                    $columnDefinition['type'] = ColumnType::from($parser->getPrimaryColumn()->getType(false));
                    $columnDefinition['searchable'] = true;
                    $columnDefinition['searchableIndexNames'] = [$manyToOneAnnotation->indexName];
                    $columnDefinition['manyToOneEntity'] = $manyToOneAnnotation->entity;
                } elseif ($oneToManyAnnotation instanceof OneToMany) {
                    $columnDefinition['oneToManyEntity'] = $oneToManyAnnotation->entity;
                    $columnDefinition['oneToManyField'] = $oneToManyAnnotation->targetField;
                } else {
                    continue;
                }

                $this->columns[$propertyReflection->getName()] = $columnDefinition;
            }

            if (!$hasPrimaryKey) {
                throw new InvalidEntityException("The entity '{$this->class}' does not have a primary key");
            }
        } catch (ReflectionException $e) {
            throw new EntityNotFoundException("The entity with class '{$this->class}' could not be loaded");
        }
    }
}
