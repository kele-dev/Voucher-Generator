<?php

namespace App\Http\Controllers;

use App\Jobs\QueueHeartbeat;
use App\Services\Backup\BackupManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController
{
    public const SCHEDULER_KEY = 'voucher:scheduler_heartbeat';

    public function __invoke(Request $request): JsonResponse
    {
        $checks = [
            'database' => ['ok' => $this->databaseOk()],
            'queue_worker' => ['ok' => $this->heartbeatOk(QueueHeartbeat::CACHE_KEY)],
            'scheduler' => ['ok' => $this->heartbeatOk(self::SCHEDULER_KEY)],
            'backup' => ['ok' => $this->backupOk()],
        ];

        $ok = ! in_array(false, array_column($checks, 'ok'), true);

        $token = (string) config('voucher.health_token');
        $provided = (string) $request->header('X-Health-Token', '');

        $payload = ['status' => $ok ? 'ok' : 'degraded'];

        if ($token !== '' && hash_equals($token, $provided)) {
            $payload['checks'] = $checks;
        }

        return response()->json($payload, $ok ? 200 : 503);
    }

    private function databaseOk(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function heartbeatOk(string $key): bool
    {
        $timestamp = Cache::get($key);

        if (! is_numeric($timestamp)) {
            return false;
        }

        return (int) $timestamp >= now()->getTimestamp() - (int) config('voucher.queue_stall_seconds', 300);
    }

    private function backupOk(): bool
    {
        $latest = app(BackupManager::class)->latest();

        if ($latest === null) {
            return false;
        }

        $retention = (int) config('voucher.export_retention_days', 30);

        return $latest['created_at']->greaterThan(now()->subDays($retention));
    }
}
