<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'location',
    ];

    /**
     * Warehouse stock records.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Stock movement history within this warehouse.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
