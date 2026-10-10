<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the append-only activity log and masks voucher codes so a code can be
 * recognised in a log without being usable.
 */
class Audit
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(string $action, ?Model $subject = null, array $payload = [], ?User $actor = null): void
    {
        $actor ??= auth()->user();

        ActivityLog::create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'payload_json' => $payload === [] ? null : $payload,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Keep a recognisable head and tail; something too short to split is
     * redacted completely so it cannot be read back.
     */
    public static function maskCode(string $code): string
    {
        if (strlen($code) < 12) {
            return str_repeat('•', strlen($code));
        }

        return substr($code, 0, 5).'…'.substr($code, -3);
    }
}
