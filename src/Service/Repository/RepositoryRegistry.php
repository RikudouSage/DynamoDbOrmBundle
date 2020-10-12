<?php

namespace Rikudou\DynamoDbOrm\Service\Repository;

use Rikudou\DynamoDbOrm\Exception\RepositoryNotFoundException;

final class RepositoryRegistry implements RepositoryRegistryInterface
{
    /**
     * @var RepositoryInterface[]
     */
    private $repositories;

    public function __construct(RepositoryInterface ...$repositories)
    {
        foreach ($repositories as $repository) {
            $this->repositories[$repository->getEntityClass()] = $repository;
        }
    }

    public function getRepository(string $entity): RepositoryInterface
    {
        if (!isset($this->repositories[$entity])) {
            throw new RepositoryNotFoundException("The repository for entity '{$entity}' does not exist");
        }

        return $this->repositories[$entity];
    }
}
