<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'bom_id',
        'line_type',
        'component_part_id',
        'work_center_id',
        'uom_id',
        'quantity',
        'notes',
        'sort_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'quantity'   => 'decimal:4',
        'sort_order' => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function componentPart(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'component_part_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
