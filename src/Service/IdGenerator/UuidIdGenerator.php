<?php

namespace Rikudou\DynamoDbOrm\Service\IdGenerator;

use Symfony\Component\Uid\Uuid;

final class UuidIdGenerator implements IdGenerator
{
    public function generateId(): string
    {
        return (string) Uuid::v4();
    }
}
