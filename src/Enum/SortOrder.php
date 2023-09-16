<?php

namespace Rikudou\DynamoDbOrm\Enum;

enum SortOrder: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
