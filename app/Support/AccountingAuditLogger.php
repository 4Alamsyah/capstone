<?php

namespace App\Support;

use App\Models\AccountingAuditTrail;
use Illuminate\Database\Eloquent\Model;

class AccountingAuditLogger
{
    public static function record(string $action, Model $subject, ?string $details = null): void
    {
        AccountingAuditTrail::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'details' => $details,
            'happened_at' => now(),
        ]);
    }
}
