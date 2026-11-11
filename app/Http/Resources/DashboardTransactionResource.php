<?php

namespace App\Http\Resources;

use App\Models\CarTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CarTransaction $transaction */
        $transaction = $this->resource;
        $data = [
            'id' => $transaction->id,
            'plate_number' => $transaction->plate_number,
            'brand' => $transaction->brand,
            'model' => $transaction->model,
            'year' => $transaction->year,
            'status' => $transaction->sell_date ? 'sold' : 'stock',
            'buy_date' => $transaction->buy_date,
            'sell_date' => $transaction->sell_date,
            'tax_status' => $transaction->taxStatus(),
            'showroom_age_status' => $transaction->showroomAgeStatus(),
            'selling_price' => $transaction->selling_price,
            'setor_price' => $transaction->setor_price,
            'opening_price' => $transaction->opening_price,
            'can_update' => $request->user()?->can('update', $transaction) ?? false,
            'can_delete' => $request->user()?->can('delete', $transaction) ?? false,
        ];

        if ($request->user()?->can('viewSensitiveData', CarTransaction::class)) {
            $data += [
                'buy_price' => $transaction->buy_price,
                'total_capital' => $transaction->total_capital,
                'gross_profit' => $transaction->gross_profit,
                'setor_profit' => (float) $transaction->setor_price - (float) $transaction->total_capital,
                'total_profit' => $transaction->calculatedTotalProfit(),
            ];
        }

        return $data;
    }
}
