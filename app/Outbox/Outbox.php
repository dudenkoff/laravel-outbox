<?php

namespace App\Outbox;

use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

class Outbox
{
    /**
     * Save an event in the same transaction as the business data.
     *
     * @throws PublishedOutsideTransaction
     */
    public static function publish(PublishableEvent $event): void
    {
        if (DB::transactionLevel() === 0) {
            throw new PublishedOutsideTransaction($event::class);
        }

        OutboxEvent::create([
            'type' => $event::class,
            'payload' => $event->toPayload(),
        ]);
    }
}
