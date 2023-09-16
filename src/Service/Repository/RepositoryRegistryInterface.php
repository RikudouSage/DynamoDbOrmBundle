<?php

namespace Rikudou\DynamoDbOrm\Service\Repository;

interface RepositoryRegistryInterface
{
    /**
     * @template TEntity of object
     *
     * @param class-string<TEntity> $entity
     *
     * @return Repository<TEntity>
     */
    public function getRepository(string $entity): Repository;
}
