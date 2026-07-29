<?php

namespace App\Actions\Orders;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Outbox\Outbox;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreateOrderAction
{
    /**
     * @throws Throwable
     */
    public function handle(string $reference, string $customerEmail, int $totalCents): Order
    {
        return DB::transaction(function () use ($reference, $customerEmail, $totalCents) {
            $order = Order::create([
                'reference' => $reference,
                'customer_email' => $customerEmail,
                'total_cents' => $totalCents,
            ]);

            Outbox::publish(new OrderCreated(
                $order->id,
                $order->reference,
                $order->customer_email,
                $order->total_cents,
            ));

            return $order;
        });
    }
}
