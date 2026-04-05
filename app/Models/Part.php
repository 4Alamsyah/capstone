<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    /** @use HasFactory<\Database\Factories\PartFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'part_number',
        'name',
        'category',
        'description',
        'selling_price',
        'safety_stock',
    ];

    /**
     * Part can have many supplier prices.
     */
    public function supplierPrices(): HasMany
    {
        return $this->hasMany(PartSupplierPrice::class);
    }

    /**
     * Convenience relation to access suppliers.
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'part_supplier_prices')
            ->withPivot('purchase_price')
            ->withTimestamps();
    }

    /**
     * Warehouse stock per part.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Stock movement history for this part.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
