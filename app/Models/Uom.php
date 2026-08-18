<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Uom extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Parts whose default UOM is this unit.
     */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'default_uom_id');
    }

    /**
     * BOM item lines using this unit.
     */
    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class, 'uom_id');
    }
}
