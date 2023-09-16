<?php

namespace Rikudou\DynamoDbOrm\Enum;

enum BeforeQuerySendEventType: string
{
    case Find = 'find';
    case FindBy = 'findBy';
    case FindOneBy = 'findOneBy';
    case FindAll = 'findAll';
}
