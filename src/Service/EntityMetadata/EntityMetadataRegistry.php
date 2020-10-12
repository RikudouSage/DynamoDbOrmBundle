<?php

namespace Rikudou\DynamoDbOrm\Service\EntityMetadata;

use Rikudou\DynamoDbOrm\Exception\EntityNotFoundException;

final class EntityMetadataRegistry
{
    /**
     * @var EntityClassMetadata[]
     */
    private $classMetadata = [];

    public function __construct(EntityClassMetadata ...$classMetadata)
    {
        foreach ($classMetadata as $metadata) {
            $this->classMetadata[$metadata->getClass()] = $metadata;
        }
    }

    /**
     * @param string $entity
     *
     * @throws EntityNotFoundException
     *
     * @return EntityClassMetadata
     */
    public function getForEntity(string $entity): EntityClassMetadata
    {
        if (!isset($this->classMetadata[$entity])) {
            throw new EntityNotFoundException("Metadata for entity '{$entity}' not found");
        }

        return $this->classMetadata[$entity];
    }
}
