<?php

namespace Rikudou\DynamoDbOrm\Service\Repository;

use Rikudou\DynamoDbOrm\Exception\RepositoryNotFoundException;

final class RepositoryRegistry implements RepositoryRegistryInterface
{
    /**
     * @var Repository<object>[]
     */
    private array $repositories;

    /**
     * @param Repository<object> ...$repositories
     */
    public function __construct(Repository ...$repositories)
    {
        foreach ($repositories as $repository) {
            $this->repositories[$repository->getEntityClass()] = $repository;
        }
    }

    /**
     * @template TEntity of object
     *
     * @param class-string<TEntity> $entity
     *
     * @return Repository<TEntity>
     */
    public function getRepository(string $entity): Repository
    {
        if (!isset($this->repositories[$entity])) {
            throw new RepositoryNotFoundException("The repository for entity '{$entity}' does not exist");
        }

        return $this->repositories[$entity]; // @phpstan-ignore-line
    }
}
