<?php

namespace Rikudou\DynamoDbOrm\Service\NameConverter;

interface NameConverterInterface
{
    public function convertForDynamoDb(string $name): string;

    public function convertFromDynamoDb(string $name): string;
}
