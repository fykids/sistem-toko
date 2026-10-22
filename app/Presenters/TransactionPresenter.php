<?php

namespace App\Presenters;

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class TransactionPresenter
{
    /**
     * @return array{
     *     transactions: Collection<int, CarTransaction>,
     *     stockCount: int,
     *     soldCount: int,
     *     recordedCapital: float|int|null,
     *     soldRevenue: float|int,
     *     soldCapital: float|int|null,
     *     soldNetProfit: float|int|null,
     *     tableColumnCount: int,
     *     canViewSensitiveData: bool
     * }
     */
    public function dashboard(User $user): array
    {
        $canViewSensitiveData = $user->can('viewSensitiveData', CarTransaction::class);
        $columns = $canViewSensitiveData
            ? ['*']
            : [
                'id',
                'plate_number',
                'brand',
                'model',
                'year',
                'sell_date',
                'buy_date',
                'tax_items',
                'selling_price',
                'setor_price',
                'opening_price',
            ];
        $transactions = CarTransaction::query()->latest()->get($columns);
        $soldTransactions = $transactions->whereNotNull('sell_date');

        return [
            'transactions' => $transactions,
            'stockCount' => $transactions->whereNull('sell_date')->count(),
            'soldCount' => $soldTransactions->count(),
            'recordedCapital' => $canViewSensitiveData ? $transactions->sum('total_capital') : null,
            'soldRevenue' => $soldTransactions->sum('selling_price'),
            'soldCapital' => $canViewSensitiveData ? $soldTransactions->sum('total_capital') : null,
            'soldNetProfit' => $canViewSensitiveData ? $soldTransactions->sum('net_profit') : null,
            'tableColumnCount' => $canViewSensitiveData ? 14 : 9,
            'canViewSensitiveData' => $canViewSensitiveData,
        ];
    }

    public function transactionDetail(CarTransaction $transaction, User $user): CarTransaction
    {
        if ($user->can('viewSensitiveData', CarTransaction::class)) {
            return $transaction;
        }

        return CarTransaction::query()
            ->select([
                'id',
                'plate_number',
                'brand',
                'model',
                'year',
                'transmission',
                'color',
                'chassis_number',
                'engine_number',
                'owner_name',
                'mileage_km',
                'manager_name',
                'buy_date',
                'sell_date',
                'vehicle_photo_path',
            ])
            ->findOrFail($transaction->getKey());
    }
}
