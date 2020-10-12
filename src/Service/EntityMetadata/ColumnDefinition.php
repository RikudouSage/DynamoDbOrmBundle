<?php

namespace Rikudou\DynamoDbOrm\Service\EntityMetadata;

use Rikudou\DynamoDbOrm\Exception\UnknownGeneratorException;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGeneratorInterface;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGeneratorRegistry;
use Rikudou\DynamoDbOrm\Service\TypeConverter;

final class ColumnDefinition
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $primary;

    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    private $searchable;

    /**
     * @var string|null
     */
    private $generator;

    /**
     * @var mixed[]
     */
    private $generatorParameters;

    /**
     * @var IdGeneratorRegistry
     */
    private $generatorRegistry;

    /**
     * @var string[]
     */
    private $searchableIndexNames;

    /**
     * @var TypeConverter
     */
    private $typeConverter;

    /**
     * @var string|null
     */
    private $manyToOneEntity;

    /**
     * @var string|null
     */
    private $oneToManyEntity;

    /**
     * @var string|null
     */
    private $oneToManyField;

    /**
     * ColumnDefinition constructor.
     *
     * @param string              $name
     * @param bool                $primary
     * @param string              $type
     * @param bool                $searchable
     * @param string[]            $searchableIndexNames
     * @param string|null         $generator
     * @param mixed[]             $generatorParameters
     * @param string|null         $manyToOneEntity
     * @param string|null         $oneToManyEntity
     * @param string|null         $oneToManyField
     * @param IdGeneratorRegistry $generatorRegistry
     * @param TypeConverter       $typeConverter
     */
    public function __construct(
        string $name,
        bool $primary,
        string $type,
        bool $searchable,
        array $searchableIndexNames,
        ?string $generator,
        array $generatorParameters,
        ?string $manyToOneEntity,
        ?string $oneToManyEntity,
        ?string $oneToManyField,
        IdGeneratorRegistry $generatorRegistry,
        TypeConverter $typeConverter
    ) {
        $this->name = $name;
        $this->primary = $primary;
        $this->type = $type;
        $this->searchable = $searchable;
        $this->generator = $generator;
        $this->generatorParameters = $generatorParameters;
        $this->generatorRegistry = $generatorRegistry;
        $this->searchableIndexNames = $searchableIndexNames;
        $this->typeConverter = $typeConverter;
        $this->manyToOneEntity = $manyToOneEntity;
        $this->oneToManyEntity = $oneToManyEntity;
        $this->oneToManyField = $oneToManyField;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function getType(bool $converted = true): string
    {
        if (!$converted) {
            return $this->type;
        }

        return $this->typeConverter->getDynamoType($this->type);
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * @return string[]
     */
    public function getSearchableIndexNames(): array
    {
        return $this->searchableIndexNames;
    }

    public function getGenerator(): ?IdGeneratorInterface
    {
        try {
            return $this->generatorRegistry->get($this->generator ?? '');
        } catch (UnknownGeneratorException $e) {
            return null;
        }
    }

    public function isManyToOne(): bool
    {
        return $this->manyToOneEntity !== null;
    }

    public function getManyToOneEntity(): ?string
    {
        return $this->manyToOneEntity;
    }

    public function isOneToMany(): bool
    {
        return $this->oneToManyField !== null && $this->oneToManyEntity !== null;
    }

    public function getOneToManyEntity(): ?string
    {
        return $this->oneToManyEntity;
    }

    public function getOneToManyField(): ?string
    {
        return $this->oneToManyField;
    }
}
