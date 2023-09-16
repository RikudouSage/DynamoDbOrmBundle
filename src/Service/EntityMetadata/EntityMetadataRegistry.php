<?php

namespace Rikudou\DynamoDbOrm\Service\EntityMetadata;

use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;

final class EntityMetadataRegistry
{
    /**
     * @var EntityClassMetadata[]
     */
    private array $classMetadata = [];

    public function __construct(EntityClassMetadata ...$classMetadata)
    {
        foreach ($classMetadata as $metadata) {
            $this->classMetadata[$metadata->class] = $metadata;
        }
    }

    /**
     * @throws EntityNotFoundException
     */
    public function getForEntity(string $entity): EntityClassMetadata
    {
        if (!isset($this->classMetadata[$entity])) {
            throw new EntityNotFoundException("Metadata for entity '{$entity}' not found");
        }

        return $this->classMetadata[$entity];
    }
}
