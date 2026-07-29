<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use App\Outbox\InvalidOutboxEvent;
use App\Outbox\PublishableEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('outbox:relay {--limit=100 : Maximum number of events to relay in one run}')]
#[Description('Dispatches committed outbox events')]
class RelayOutbox extends Command
{
    public function handle(): int
    {
        $pending = OutboxEvent::pending()
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        $relayed = 0;
        $failed = 0;

        foreach ($pending as $record) {
            try {
                $event = $this->toEvent($record);
            } catch (Throwable $exception) {
                // Only this record is broken, so mark it and keep going.
                $record->markFailed($exception);

                report($exception);

                $failed++;

                continue;
            }

            // Not guarded on purpose. Listeners are queued, so a failure here
            // means the queue is unreachable, which affects every record. The
            // exception aborts the run and the rest stay pending for next time.
            event($event);

            $record->markPublished();

            $relayed++;
        }

        $this->info("Relayed {$relayed} event(s), {$failed} failed.");

        return self::SUCCESS;
    }

    /**
     * @throws InvalidOutboxEvent
     */
    private function toEvent(OutboxEvent $record): PublishableEvent
    {
        $type = $record->type;

        if (! is_subclass_of($type, PublishableEvent::class)) {
            throw new InvalidOutboxEvent($record->id, $type);
        }

        return $type::fromPayload($record->payload);
    }
}
