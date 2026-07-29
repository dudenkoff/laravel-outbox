<?php

namespace App\Outbox;

use RuntimeException;

/**
 * Thrown when the stored type is not a PublishableEvent class,
 * for example after the class was renamed or deleted.
 */
class InvalidOutboxEvent extends RuntimeException
{
    public function __construct(int $id, string $type)
    {
        parent::__construct("Outbox event [{$id}] holds type [{$type}], which is not a ".PublishableEvent::class.'.');
    }
}
