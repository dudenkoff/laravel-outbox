<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('outbox:prune {--days=7 : Keep relayed events for this many days}')]
#[Description('Deletes outbox events that have already been relayed')]
class PruneOutbox extends Command
{
    public function handle(): int
    {
        $deleted = OutboxEvent::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<', now()->subDays((int) $this->option('days')))
            ->delete();

        $this->info("Pruned {$deleted} relayed event(s).");

        return self::SUCCESS;
    }
}
