<?php

namespace Rikudou\DynamoDbOrm\Service\Repository;

interface RepositoryRegistryInterface
{
    public function getRepository(string $entity): RepositoryInterface;
}
