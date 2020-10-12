<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class OneToMany
{
    /**
     * @Required()
     *
     * @var string
     */
    public $entity;

    /**
     * @Required()
     *
     * @var string
     */
    public $targetField;
}
