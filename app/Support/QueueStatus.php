<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Detects a database queue that has stalled: work is waiting but no worker is
 * alive to pick it up.
 */
class QueueStatus
{
    public static function stalled(): bool
    {
        if (config('queue.default') !== 'database') {
            return false;
        }

        $stall = (int) config('voucher.queue_stall_seconds', 300);
        $retryAfter = (int) config('queue.connections.database.retry_after', 2000);
        $now = now()->getTimestamp();

        $jobs = DB::table('jobs');

        // A live worker holds a reservation younger than retry_after; a stale
        // one belongs to a crashed worker and does not count as "running".
        $workerBusy = (clone $jobs)
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '>', $now - $retryAfter)
            ->exists();

        if ($workerBusy) {
            return false;
        }

        return $jobs
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $now - $stall)
            ->exists();
    }
}
