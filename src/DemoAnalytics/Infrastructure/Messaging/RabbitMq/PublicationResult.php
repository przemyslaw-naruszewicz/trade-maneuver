<?php

declare(strict_types=1);

namespace TradeManeuver\DemoAnalytics\Infrastructure\Messaging\RabbitMq;

/**
 * How RabbitMQ answered one publication on a channel in confirm mode.
 */
enum PublicationResult: string
{
    /** RabbitMQ confirmed the message and routed it to a queue. */
    case Confirmed = 'confirmed';

    /** RabbitMQ returned the mandatory message because no queue is bound to the exchange. */
    case Returned = 'could not route';

    /** RabbitMQ refused the message with a negative confirmation. */
    case Refused = 'refused';

    /** No confirmation arrived in time. */
    case Unconfirmed = 'did not confirm';
}
