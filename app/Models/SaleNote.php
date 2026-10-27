<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleNote extends Model
{
    protected $fillable = [
        'car_transaction_id',
        'nama_konsumen',
        'buyer_nik',
        'buyer_phone',
        'buyer_address',
        'created_by',
    ];

    public function carTransaction(): BelongsTo
    {
        return $this->belongsTo(CarTransaction::class, 'car_transaction_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class, 'sale_note_id');
    }

    public function paymentStatus(): string
    {
        $vehicle = $this->carTransaction;

        if ((float) $vehicle->remaining_payment <= 0) {
            return 'paid';
        }

        if (
            (float) $vehicle->leasing_disbursement > 0
            || $this->payments->contains('payment_method', 'leasing')
        ) {
            return 'leasing';
        }

        return 'unpaid';
    }

    public function totalPaidAmount(): float
    {
        $vehicle = $this->carTransaction;

        return round(
            (float) $vehicle->down_payment
                + (float) $vehicle->leasing_disbursement
                + (float) $vehicle->settlement_amount,
            2,
        );
    }
}
