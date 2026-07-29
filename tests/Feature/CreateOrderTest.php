<?php

use App\Actions\Orders\CreateOrderAction;
use App\Events\OrderCreated;
use App\Listeners\SendOrderConfirmation;
use App\Models\Order;
use App\Models\OutboxEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

function createOrder(string $reference = 'ORD-1'): Order
{
    return app(CreateOrderAction::class)->handle($reference, 'customer@example.test', 12_900);
}

test('creating an order publishes a snapshot of it to the outbox', function () {
    $order = createOrder();

    expect(OutboxEvent::sole())
        ->type->toBe(OrderCreated::class)
        ->payload->toBe([
            'orderId' => $order->id,
            'reference' => 'ORD-1',
            'customerEmail' => 'customer@example.test',
            'totalCents' => 12_900,
        ]);
});

test('a failure further up the transaction leaves neither the order nor the event', function () {
    rescue(fn () => DB::transaction(function () {
        createOrder();

        throw new RuntimeException('the payment gateway exploded');
    }), report: false);

    expect(Order::count())->toBe(0)
        ->and(OutboxEvent::count())->toBe(0);
});

test('relaying a stored event turns it into a queued listener job', function () {
    Queue::fake();

    createOrder();

    $this->artisan('outbox:relay')->assertSuccessful();

    Queue::assertPushed(
        CallQueuedListener::class,
        fn (CallQueuedListener $job) => $job->class === SendOrderConfirmation::class,
    );
});
