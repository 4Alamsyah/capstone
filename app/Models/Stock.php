<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'part_id',
        'warehouse_id',
        'quantity',
    ];

    /**
     * Stock belongs to a part.
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * Stock belongs to a warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Stock movement history for the same part/warehouse pair.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'part_id', 'part_id')
            ->where('warehouse_id', $this->warehouse_id);
    }
}
