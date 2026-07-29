<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * The outbox delivers at least once, so this listener can run twice
 * for the same order and has to be idempotent.
 */
class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderCreated $event): void
    {
        Log::info('Order confirmation sent.', [
            'order_id' => $event->orderId,
            'reference' => $event->reference,
            'customer_email' => $event->customerEmail,
        ]);
    }
}
