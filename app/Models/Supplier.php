<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
    ];

    /**
     * Supplier can have many part prices.
     */
    public function partPrices(): HasMany
    {
        return $this->hasMany(PartSupplierPrice::class);
    }

    /**
     * Convenience relation to access parts.
     */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'part_supplier_prices')
            ->withPivot('purchase_price')
            ->withTimestamps();
    }
}
