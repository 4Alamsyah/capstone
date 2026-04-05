<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderLog extends Model
{
    protected $fillable = [
        'work_order_id',
        'user_id',
        'log_type',
        'title',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
