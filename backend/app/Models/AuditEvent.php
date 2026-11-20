<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class AuditEvent
{
    public static function record(string $action, string $subjectType, int $subjectId): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'created_at' => now(),
        ]);
    }
}
