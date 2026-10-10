<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Heartbeat the queue worker writes every minute; /health reads it to tell a
 * running worker from a dead one.
 */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public const CACHE_KEY = 'voucher:queue_heartbeat';

    public int $timeout = 60;

    public function handle(): void
    {
        Cache::forever(self::CACHE_KEY, now()->getTimestamp());
    }
}
