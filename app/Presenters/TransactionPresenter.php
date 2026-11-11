<?php

namespace App\Presenters;

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TransactionPresenter
{
    /**
     * @return array{
     *     transactions: LengthAwarePaginator<int, CarTransaction>,
     *     view: string,
     *     search: string,
     *     startDate: string|null,
     *     endDate: string|null,
     *     canViewSensitiveData: bool
     * }
     */
    public function dashboard(User $user, array $filters = []): array
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
        $view = $filters['view'] ?? 'dashboard';
        $search = trim($filters['q'] ?? '');
        $transactionsQuery = CarTransaction::query()
            ->when($view === 'inventory', fn ($query) => $query->whereNull('sell_date'))
            ->when(in_array($view, ['sales', 'reports'], true), fn ($query) => $query->whereNotNull('sell_date'))
            ->when($view === 'reports' && filled($filters['start_date'] ?? null), fn ($query) => $query->whereDate('sell_date', '>=', $filters['start_date']))
            ->when($view === 'reports' && filled($filters['end_date'] ?? null), fn ($query) => $query->whereDate('sell_date', '<=', $filters['end_date']))
            ->when($search !== '', function ($query) use ($search): void {
                $searchPattern = '%'.$search.'%';
                $query->where(function ($query) use ($searchPattern): void {
                    $query->where('plate_number', 'like', $searchPattern)
                        ->orWhere('brand', 'like', $searchPattern)
                        ->orWhere('model', 'like', $searchPattern)
                        ->orWhere('year', 'like', $searchPattern);
                });
            });
        $transactions = $transactionsQuery
            ->latest()
            ->orderByDesc('id')
            ->paginate(10, $columns)
            ->withQueryString();

        return [
            'transactions' => $transactions,
            'view' => $view,
            'search' => $search,
            'startDate' => $filters['start_date'] ?? null,
            'endDate' => $filters['end_date'] ?? null,
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
