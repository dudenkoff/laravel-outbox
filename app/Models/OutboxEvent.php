<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

#[Fillable('type', 'payload')]
class OutboxEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'published_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Events the relay has not dispatched yet.
     */
    #[Scope]
    protected function pending(Builder $query): Builder
    {
        return $query->whereNull('published_at')->whereNull('failed_at');
    }

    /**
     * Events that can never be dispatched and need a human to look at them.
     */
    #[Scope]
    protected function failed(Builder $query): Builder
    {
        return $query->whereNotNull('failed_at');
    }

    public function markPublished(): void
    {
        $this->published_at = now();

        $this->save();
    }

    public function markFailed(Throwable $exception): void
    {
        $this->failed_at = now();
        $this->last_error = $exception->getMessage();

        $this->save();
    }
}
