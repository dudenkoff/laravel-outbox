<?php

namespace App\Events;

use App\Outbox\PublishableEvent;

/**
 * A snapshot of the order at the moment it was created.
 *
 * Listeners run later, so the event carries the data itself instead of only
 * an id. Loading the order by id later could return a different state than
 * the one this event describes.
 *
 * Not Dispatchable on purpose: these events must go through the outbox,
 * not through a direct dispatch.
 *
 * Readonly because a published event should never change.
 */
final readonly class OrderCreated implements PublishableEvent
{
    public function __construct(
        public int $orderId,
        public string $reference,
        public string $customerEmail,
        public int $totalCents,
    ) {}

    public function toPayload(): array
    {
        return [
            'orderId' => $this->orderId,
            'reference' => $this->reference,
            'customerEmail' => $this->customerEmail,
            'totalCents' => $this->totalCents,
        ];
    }

    public static function fromPayload(array $payload): static
    {
        return new self(
            $payload['orderId'],
            $payload['reference'],
            $payload['customerEmail'],
            $payload['totalCents'],
        );
    }
}
