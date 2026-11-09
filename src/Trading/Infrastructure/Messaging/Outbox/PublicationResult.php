<?php

declare(strict_types=1);

namespace TradeManeuver\Trading\Infrastructure\Messaging\Outbox;

enum PublicationResult
{
    /** RabbitMQ confirmed the message and routed it to a queue. */
    case Confirmed;

    /** RabbitMQ returned the mandatory message because no queue is bound for its routing key. */
    case Returned;

    /** RabbitMQ refused the message, or no confirmation arrived in time. */
    case Unconfirmed;
}
