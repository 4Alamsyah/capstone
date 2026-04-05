<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartSupplierPrice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'part_id',
        'supplier_id',
        'purchase_price',
    ];

    /**
     * Price belongs to a part.
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * Price belongs to a supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
