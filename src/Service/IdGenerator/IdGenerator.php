<?php

namespace Rikudou\DynamoDbOrm\Service\IdGenerator;

interface IdGenerator
{
    public function generateId(): mixed;
}
