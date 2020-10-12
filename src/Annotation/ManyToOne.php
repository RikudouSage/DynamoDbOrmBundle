<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Doctrine\Common\Annotations\Annotation\Required;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class ManyToOne
{
    /**
     * @Required()
     *
     * @var string
     */
    public $entity;

    /**
     * @var string
     */
    public $joinColumn;

    /**
     * @var string
     */
    public $indexName;
}
