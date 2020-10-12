<?php

namespace Rikudou\DynamoDbOrm\Service\IdGenerator;

interface IdGeneratorInterface
{
    /**
     * @return mixed
     */
    public function generateId();
}
