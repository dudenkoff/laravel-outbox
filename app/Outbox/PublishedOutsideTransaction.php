<?php

namespace App\Outbox;

use RuntimeException;

/**
 * Thrown when Outbox::publish() is called outside a transaction.
 * The event has to be saved in the same transaction as the business data.
 */
class PublishedOutsideTransaction extends RuntimeException
{
    public function __construct(string $event)
    {
        parent::__construct("[{$event}] was published outside of a database transaction.");
    }
}
