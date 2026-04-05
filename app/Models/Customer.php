<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'shipping_address',
        'payment_terms',
        'currency_code',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(CustomerOrder::class);
    }
}
