# Transactional outbox in Laravel

A Laravel 13 demo of publishing domain events reliably when the work that produced them
happens inside a database transaction. The pattern lives in `app/Outbox`, with an order
flow next to it as the usage example.

## The problem

```php
DB::transaction(function () use ($attributes) {
    $order = Order::create($attributes);

    SendOrderConfirmation::dispatch($order);
});
```

The job can reach the queue before the transaction commits. A worker then looks for an
order that is not visible yet, or one that was rolled back a moment later. Laravel solves
that half with `dispatch()->afterCommit()`.

The other half has no framework answer. The transaction commits, then the process dies
before the job is pushed. Redis is unreachable, the container is killed, the request
times out. The order exists, nothing is queued, and nothing will ever retry, because the
intent was never written down anywhere.

## How it works

```
CreateOrderAction
  └─ transaction
       ├─ INSERT INTO orders
       └─ INSERT INTO outbox_events     <- same transaction, same fate

outbox:relay (scheduled every minute)
  ├─ SELECT pending events
  ├─ event(OrderCreated)                <- ordinary Laravel event
  └─ mark published

queued listener -> jobs table -> worker
```

The relay does not build jobs. It fires a normal event, and any listener marked
`ShouldQueue` becomes a job through the usual machinery.

## Running it

Needs PHP 8.3+. Uses SQLite and the database queue driver, so nothing else has to run.

```bash
composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate

php artisan tinker --execute 'app(App\Actions\Orders\CreateOrderAction::class)
    ->handle("ORD-1001", "customer@example.com", 12900);'

php artisan outbox:relay        # Relayed 1 event(s), 0 failed.
php artisan queue:work --once   # SendOrderConfirmation .. DONE
php artisan test                # 18 tests
```

## Decisions

**Events carry a snapshot, not just an id.** A listener runs minutes after the fact, and
loading the order by id at that point returns current state, not the state the event
describes.

**`toPayload` and `fromPayload` are written by hand.** Deriving them through reflection
would silently break every stored row the moment a property is renamed. The explicit
mapping is where message versions get handled.

**No retry counter.** An earlier version capped attempts at three. A four minute Redis
outage burned the counter on every pending row at once and parked the whole backlog with
no way back. Now a broken message fails on the first run and waits for a human, while a
broken queue aborts the run and leaves everything pending for the next one.

**`Outbox::publish()` refuses to run outside a transaction.** That one mistake turns the
pattern back into the problem it solves, so it throws instead.

## Known limits

- One relay at a time, through `withoutOverlapping()`. Running several would need
  `SELECT ... FOR UPDATE SKIP LOCKED`, which SQLite does not have.
- Up to a minute of delay, since the relay runs on the scheduler.
- Delivery is at least once, so listeners have to be idempotent.
- Failed events need manual attention. `OutboxEvent::failed()` is the query, there is no
  command to requeue them.
- Listeners have to be queued. A synchronous one that throws would stop the whole relay
  instead of just itself.
