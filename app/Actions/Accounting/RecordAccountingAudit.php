<?php

namespace App\Actions\Accounting;

use Illuminate\Support\Facades\DB;

class RecordAccountingAudit
{
    /**
     * @param  array<array-key, mixed>|null  $before
     * @param  array<array-key, mixed>|null  $after
     */
    public function record(
        int $teamId,
        ?int $actorId,
        string $action,
        string $subjectType,
        ?int $subjectId,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): void {
        DB::table('accounting_audit_events')->insert([
            'team_id' => $teamId,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reason' => $reason,
            'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'after' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
