<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolLoan extends Model
{
    use HasFactory;

    public const STATUS_BORROWED = 'borrowed';

    public const STATUS_RETURNED = 'returned';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'part_id',
        'warehouse_id',
        'created_by',
        'returned_by',
        'borrower_name',
        'borrowed_quantity',
        'returned_quantity',
        'borrowed_at',
        'due_at',
        'returned_at',
        'status',
        'notes',
    ];

    /**
     * Cast attributes for convenient date handling.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'borrowed_at' => 'datetime',
        'due_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    /**
     * Part being borrowed.
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    /**
     * Warehouse where tool is checked out from.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * User who recorded the borrowing transaction.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who recorded the return transaction.
     */
    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /**
     * Remaining quantity that has not been returned yet.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, (int) $this->borrowed_quantity - (int) $this->returned_quantity);
    }
}
