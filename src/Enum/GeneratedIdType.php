<?php

namespace Rikudou\DynamoDbOrm\Enum;

enum GeneratedIdType: string
{
    case Uuid = 'uuid';
    case RandomString = 'randomString';
    case Custom = 'custom';
}
