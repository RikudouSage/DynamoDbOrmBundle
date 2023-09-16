<?php

namespace Rikudou\DynamoDbOrm\Service\NameConverter;

interface NameConverter
{
    public function convertForDynamoDb(string $name): string;

    public function convertFromDynamoDb(string $name): string;
}
