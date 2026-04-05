<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bom extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'part_id',
        'name',
        'description',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class)->orderBy('sort_order');
    }
}
