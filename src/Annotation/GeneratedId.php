<?php

namespace Rikudou\DynamoDbOrm\Annotation;

use Doctrine\Common\Annotations\Annotation\Enum;

/**
 * @Annotation
 */
final class GeneratedId
{
    /**
     * @Enum({"uuid", "randomString", "custom"})
     *
     * @var string
     */
    public $type = 'uuid';

    /**
     * @var int
     */
    public $randomStringLength = 20;

    /**
     * @var string
     */
    public $customGenerator = null;
}
