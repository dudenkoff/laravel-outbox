<?php

namespace App\Outbox;

/**
 * An event that can be saved to the outbox and rebuilt from it later.
 *
 * The payload is stored as JSON, so it should only contain plain values.
 */
interface PublishableEvent
{
    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): static;
}
