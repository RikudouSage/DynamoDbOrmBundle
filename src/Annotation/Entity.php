<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 */
final class Entity
{
    /**
     * @Required()
     *
     * @var string
     */
    public $table;
}
