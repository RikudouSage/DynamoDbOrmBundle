<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class SearchableColumn
{
    /**
     * @var string
     */
    public $indexName;
}
