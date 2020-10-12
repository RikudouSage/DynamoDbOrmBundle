<?php

namespace Rikudou\DynamoDbOrm\Service\EntityMetadata;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;
use ReflectionException;
use Rikudou\DynamoDbOrm\Annotation\Column;
use Rikudou\DynamoDbOrm\Annotation\Entity;
use Rikudou\DynamoDbOrm\Annotation\GeneratedId;
use Rikudou\DynamoDbOrm\Annotation\ManyToOne;
use Rikudou\DynamoDbOrm\Annotation\OneToMany;
use Rikudou\DynamoDbOrm\Annotation\PrimaryKey;
use Rikudou\DynamoDbOrm\Annotation\SearchableColumn;
use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;
use Rikudou\DynamoDbOrm\Exception\InvalidEntityException;
use Rikudou\DynamoDbOrm\Exception\UnknownColumnException;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGeneratorRegistry;
use Rikudou\DynamoDbOrm\Service\NameConverter\NameConverterInterface;
use Rikudou\DynamoDbOrm\Service\TableNameConverter;
use Rikudou\DynamoDbOrm\Service\TypeConverter;

final class EntityClassMetadata
{
    /**
     * @var string
     */
    private $class;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $primaryKeyIndex;

    /**
     * @var array<string,array<string,mixed>>
     */
    private $columns = [];

    /**
     * @var NameConverterInterface
     */
    private $nameConverter;

    /**
     * @var IdGeneratorRegistry
     */
    private $idGeneratorRegistry;

    /**
     * @var TypeConverter
     */
    private $typeConverter;

    /**
     * @var TableNameConverter
     */
    private $tableNameConverter;

    public function __construct(
        string $class,
        NameConverterInterface $nameConverter,
        IdGeneratorRegistry $idGeneratorRegistry,
        TypeConverter $typeConverter,
        TableNameConverter $tableNameConverter
    ) {
        $this->class = $class;
        $this->nameConverter = $nameConverter;
        $this->idGeneratorRegistry = $idGeneratorRegistry;
        $this->typeConverter = $typeConverter;
        $this->tableNameConverter = $tableNameConverter;
        $this->parse();
    }

    public function getClass(): string
    {
        return $this->class;
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
        $annotationReader = new AnnotationReader();

        try {
            $classReflection = new ReflectionClass($this->class);
            $entityAnnotation = $annotationReader->getClassAnnotation($classReflection, Entity::class);
            if (!$entityAnnotation instanceof Entity) {
                throw new InvalidEntityException("The entity '{$this->class}' must contain @Entity annotation");
            }
            $this->table = $this->tableNameConverter->getName($entityAnnotation->table);

            $hasPrimaryKey = false;
            foreach ($classReflection->getProperties() as $propertyReflection) {
                $columnDefinition = [
                    'name' => '',
                    'primary' => false,
                    'type' => '',
                    'searchable' => false,
                    'searchableIndexNames' => [],
                    'generator' => null,
                    'generatorParameters' => [],
                    'manyToOneEntity' => null,
                    'oneToManyEntity' => null,
                    'oneToManyField' => null,
                ];

                $columnAnnotation = $annotationReader->getPropertyAnnotation($propertyReflection, Column::class);
                $oneToManyAnnotation = $annotationReader->getPropertyAnnotation($propertyReflection, OneToMany::class);
                $manyToOneAnnotation = $annotationReader->getPropertyAnnotation($propertyReflection, ManyToOne::class);
                if ($columnAnnotation instanceof Column) {
                    if ($columnAnnotation->name === null) {
                        $columnAnnotation->name = $this->nameConverter->convertForDynamoDb($propertyReflection->getName());
                    }
                    $columnDefinition['name'] = $columnAnnotation->name;
                    $columnDefinition['type'] = $columnAnnotation->type;

                    $primaryKeyAnnotation = $annotationReader->getPropertyAnnotation($propertyReflection, PrimaryKey::class);
                    if ($primaryKeyAnnotation !== null) {
                        if ($hasPrimaryKey) {
                            throw new InvalidEntityException("Entity '{$this->class}' cannot have more than one primary key");
                        }
                        $this->primaryKeyIndex = $propertyReflection->getName();
                        $hasPrimaryKey = true;
                        $columnDefinition['primary'] = true;
                        $columnDefinition['searchable'] = true;
                    }

                    $generatorAnnotation = $annotationReader->getPropertyAnnotation($propertyReflection, GeneratedId::class);
                    if ($generatorAnnotation instanceof GeneratedId) {
                        if ($generatorAnnotation->type === 'custom' && $generatorAnnotation->customGenerator === null) {
                            throw new InvalidEntityException("The generator for '{$propertyReflection->getName()}' of entity '{$this->class}' is set to custom but no custom generator is set");
                        }
                        if ($generatorAnnotation->type === 'randomString') {
                            if ($generatorAnnotation->randomStringLength <= 0 || $generatorAnnotation->randomStringLength % 2 !== 0) {
                                throw new InvalidEntityException("The randomString generator for '{$propertyReflection->getName()}' of entity '{$this->class}' must be greater than zero and divisible by 2");
                            }
                            $columnDefinition['generatorParameters'][] = $generatorAnnotation->randomStringLength;
                        }

                        $columnDefinition['generator'] = $generatorAnnotation->type === 'custom'
                            ? $generatorAnnotation->customGenerator
                            : $generatorAnnotation->type;
                    }

                    $searchableAnnotations = $annotationReader->getPropertyAnnotations($propertyReflection);
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
                        $this->nameConverter,
                        $this->idGeneratorRegistry,
                        $this->typeConverter,
                        $this->tableNameConverter
                    );
                    $columnDefinition['type'] = $parser->getPrimaryColumn()->getType(false);
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
