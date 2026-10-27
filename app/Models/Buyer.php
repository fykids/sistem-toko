<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Buyer extends Model
{
    protected $fillable = [
        'car_transaction_id',
        'buyer_name',
        'buyer_nik',
        'buyer_phone',
        'buyer_address',
        'sell_price_deal',
        'payment_method',
        'sell_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sell_date' => 'date:Y-m-d',
            'sell_price_deal' => 'decimal:2',
        ];
    }

    public function carTransaction(): BelongsTo
    {
        return $this->belongsTo(CarTransaction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
