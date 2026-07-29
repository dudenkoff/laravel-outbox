<?php

use App\Models\OutboxEvent;
use App\Outbox\Outbox;
use App\Outbox\PublishableEvent;
use App\Outbox\PublishedOutsideTransaction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * RefreshDatabase is not used here because it wraps every test in its own
 * transaction. That would break the "outside a transaction" test and the
 * rollback test.
 */
uses(DatabaseMigrations::class);

class OrderPlaced implements PublishableEvent
{
    public function __construct(public int $orderId, public string $reference) {}

    public function toPayload(): array
    {
        return [
            'order_id' => $this->orderId,
            'reference' => $this->reference,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new static($payload['order_id'], $payload['reference']);
    }
}

test('publishing inside a transaction stores the event type and payload', function () {
    DB::transaction(fn () => Outbox::publish(new OrderPlaced(42, 'ORD-42')));

    $record = OutboxEvent::sole();

    expect($record->type)->toBe(OrderPlaced::class)
        ->and($record->payload)->toBe(['order_id' => 42, 'reference' => 'ORD-42'])
        ->and($record->published_at)->toBeNull();
});

test('publishing outside a transaction is refused', function () {
    Outbox::publish(new OrderPlaced(42, 'ORD-42'));
})->throws(PublishedOutsideTransaction::class);

test('a rolled back transaction leaves nothing in the outbox', function () {
    rescue(fn () => DB::transaction(function () {
        Outbox::publish(new OrderPlaced(42, 'ORD-42'));

        throw new RuntimeException('something went wrong after publishing');
    }), report: false);

    expect(OutboxEvent::count())->toBe(0);
});

test('the relay dispatches pending events and marks them published', function () {
    Event::fake();

    OutboxEvent::create([
        'type' => OrderPlaced::class,
        'payload' => ['order_id' => 42, 'reference' => 'ORD-42'],
    ]);

    $this->artisan('outbox:relay')->assertSuccessful();

    Event::assertDispatched(
        OrderPlaced::class,
        fn (OrderPlaced $event) => $event->orderId === 42 && $event->reference === 'ORD-42',
    );

    expect(OutboxEvent::pending()->count())->toBe(0);
});

test('the relay dispatches events in the order they were published', function () {
    Event::fake();

    foreach ([1, 2, 3] as $orderId) {
        OutboxEvent::create([
            'type' => OrderPlaced::class,
            'payload' => ['order_id' => $orderId, 'reference' => "ORD-{$orderId}"],
        ]);
    }

    $this->artisan('outbox:relay')->assertSuccessful();

    $dispatched = Event::dispatched(OrderPlaced::class)
        ->map(fn (array $args) => $args[0]->orderId)
        ->all();

    expect($dispatched)->toBe([1, 2, 3]);
});

test('the relay leaves already published events alone', function () {
    Event::fake();

    OutboxEvent::create([
        'type' => OrderPlaced::class,
        'payload' => ['order_id' => 42, 'reference' => 'ORD-42'],
    ])->markPublished();

    $this->artisan('outbox:relay')->assertSuccessful();

    Event::assertNotDispatched(OrderPlaced::class);
});

test('an event that cannot be rebuilt is failed on the first run', function () {
    OutboxEvent::create([
        'type' => 'App\Events\LongGone',
        'payload' => [],
    ]);

    $this->artisan('outbox:relay')->assertSuccessful();

    $record = OutboxEvent::sole();

    expect($record->failed_at)->not->toBeNull()
        ->and($record->published_at)->toBeNull()
        ->and($record->last_error)->toContain('LongGone')
        ->and(OutboxEvent::pending()->count())->toBe(0);
});

test('a broken event does not stop the ones behind it', function () {
    Event::fake();

    OutboxEvent::create(['type' => 'App\Events\LongGone', 'payload' => []]);
    OutboxEvent::create([
        'type' => OrderPlaced::class,
        'payload' => ['order_id' => 42, 'reference' => 'ORD-42'],
    ]);

    $this->artisan('outbox:relay')->assertSuccessful();

    Event::assertDispatched(OrderPlaced::class);

    expect(OutboxEvent::failed()->count())->toBe(1);
});

test('a failure while dispatching aborts the run and leaves every event pending', function () {
    Event::listen(OrderPlaced::class, fn () => throw new RuntimeException('the queue is unreachable'));

    foreach ([1, 2, 3] as $orderId) {
        OutboxEvent::create([
            'type' => OrderPlaced::class,
            'payload' => ['order_id' => $orderId, 'reference' => "ORD-{$orderId}"],
        ]);
    }

    expect(fn () => Artisan::call('outbox:relay'))
        ->toThrow(RuntimeException::class, 'the queue is unreachable');

    expect(OutboxEvent::pending()->count())->toBe(3)
        ->and(OutboxEvent::failed()->count())->toBe(0);
});

test('pruning removes relayed events past the retention window and keeps the rest', function () {
    $stale = OutboxEvent::create([
        'type' => OrderPlaced::class,
        'payload' => ['order_id' => 1, 'reference' => 'ORD-1'],
    ]);
    $stale->forceFill(['published_at' => now()->subDays(30)])->save();

    $recentlyRelayed = OutboxEvent::create([
        'type' => OrderPlaced::class,
        'payload' => ['order_id' => 2, 'reference' => 'ORD-2'],
    ]);
    $recentlyRelayed->markPublished();

    $stillPending = OutboxEvent::create([
        'type' => OrderPlaced::class,
        'payload' => ['order_id' => 3, 'reference' => 'ORD-3'],
    ]);

    $this->artisan('outbox:prune')->assertSuccessful();

    expect(OutboxEvent::orderBy('id')->pluck('id')->all())->toBe([$recentlyRelayed->id, $stillPending->id]);
});
