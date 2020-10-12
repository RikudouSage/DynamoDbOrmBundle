<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 */
final class Column
{
    /**
     * @var string
     */
    public $name = null;

    /**
     * @Enum({"string", "number", "binary", "boolean", "array", "json"})
     * @Required()
     *
     * @var string
     */
    public $type;
}
