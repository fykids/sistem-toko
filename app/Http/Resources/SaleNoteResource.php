<?php

namespace App\Http\Resources;

use App\Models\CarTransaction;
use App\Models\SaleNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SaleNote $saleNote */
        $saleNote = $this->resource;

        return [
            'id' => $saleNote->id,
            'nama_konsumen' => $saleNote->nama_konsumen,
            'buyer_nik' => $saleNote->buyer_nik,
            'buyer_phone' => $saleNote->buyer_phone,
            'buyer_address' => $saleNote->buyer_address,
            'created_by' => $saleNote->created_by,
            'payment_status' => $saleNote->paymentStatus(),
            'total_paid' => $saleNote->totalPaidAmount(),
            'can_view_sensitive_data' => $request->user()
                ? $request->user()->can('viewSensitiveData', CarTransaction::class)
                : false,
            'car_transaction' => $saleNote->relationLoaded('carTransaction')
                ? $saleNote->carTransaction->attributesToArray()
                : null,
            'payments' => $saleNote->relationLoaded('payments')
                ? $saleNote->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->payment_date?->toDateString(),
                    'notes' => $payment->notes,
                ])->values()
                : [],
        ];
    }
}
