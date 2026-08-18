<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    /** @use HasFactory<\Database\Factories\PartFactory> */
    use HasFactory;

    public const INVENTORY_TYPE_MATERIAL = 'material';

    public const INVENTORY_TYPE_TOOL = 'tool';

    public const CATEGORY_PURCHASE = 'purchase';

    public const CATEGORY_MANUFACTURE = 'manufacture';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'part_number',
        'name',
        'category',
        'inventory_type',
        'default_uom_id',
        'description',
        'selling_price',
        'safety_stock',
    ];

    /**
     * The unit of measure this part is stocked/produced in by default.
     */
    public function defaultUom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'default_uom_id');
    }

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

    /**
     * Tool borrowing history for this part.
     */
    public function toolLoans(): HasMany
    {
        return $this->hasMany(ToolLoan::class);
    }

    /**
     * BOMs that produce this part (i.e. this part is a sub-assembly).
     */
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    /**
     * The active BOM used to manufacture this part, if it's a sub-assembly.
     */
    public function activeBom(): ?Bom
    {
        return $this->boms()->where('is_active', true)->latest()->first();
    }

    /**
     * Whether this part is bought rather than made. Requires both: the part must be
     * explicitly declared category=purchase (the flag that also gates whether it
     * needs a supplier price on file), and it must not have an active BOM of its
     * own - a part with an active BOM is manufactured via Work Order and must never
     * be sourced through a Purchase Voucher / Purchase Order, regardless of how its
     * category happens to be set.
     */
    public function isPurchasable(): bool
    {
        return $this->category === self::CATEGORY_PURCHASE && $this->activeBom() === null;
    }
}
