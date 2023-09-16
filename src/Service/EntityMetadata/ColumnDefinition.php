<?php

namespace Rikudou\DynamoDbOrm\Service\EntityMetadata;

use Rikudou\DynamoDbOrm\Enum\ColumnType;
use Rikudou\DynamoDbOrm\Enum\GeneratedIdType;
use Rikudou\DynamoDbOrm\Exception\UnknownGeneratorException;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGenerator;
use Rikudou\DynamoDbOrm\Service\IdGenerator\IdGeneratorRegistry;
use Rikudou\DynamoDbOrm\Service\TypeConverter;

final readonly class ColumnDefinition
{
    /**
     * ColumnDefinition constructor.
     *
     * @param array<string>     $searchableIndexNames
     * @param array<mixed>      $generatorParameters
     * @param class-string|null $manyToOneEntity
     * @param class-string|null $oneToManyEntity
     */
    public function __construct(
        public string $name,
        public bool $primary,
        private ColumnType $type,
        public bool $searchable,
        public array $searchableIndexNames,
        private string|GeneratedIdType|null $generator,
        public array $generatorParameters,
        public ?string $manyToOneEntity,
        public ?string $oneToManyEntity,
        public ?string $oneToManyField,
        private IdGeneratorRegistry $generatorRegistry,
        private TypeConverter $typeConverter,
    ) {
    }

    public function getType(bool $converted = true): string
    {
        if (!$converted) {
            return $this->type->value;
        }

        return $this->typeConverter->getDynamoType($this->type);
    }

    public function getGenerator(): ?IdGenerator
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

    public function isOneToMany(): bool
    {
        return $this->oneToManyField !== null && $this->oneToManyEntity !== null;
    }
}
