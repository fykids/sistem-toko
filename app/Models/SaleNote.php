<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleNote extends Model
{
    protected $fillable = [
        'car_transaction_id',
        'buyer_name',
        'buyer_nik',
        'buyer_phone',
        'buyer_address',
        'deal_price',
        'payment_method',
        'sell_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'deal_price' => 'decimal:2',
            'sell_date' => 'date',
        ];
    }

    public function carTransaction(): BelongsTo
    {
        return $this->belongsTo(CarTransaction::class, 'car_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
