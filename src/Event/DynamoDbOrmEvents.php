<?php

namespace Rikudou\DynamoDbOrm\Event;

final class DynamoDbOrmEvents
{
    /**
     * @Event("Rikudou\DynamoDbOrm\Event\BeforeQuerySendEvent")
     */
    public const BEFORE_QUERY_SEND = 'rikudou.dynamo_db_orm.before_query_send';
}
