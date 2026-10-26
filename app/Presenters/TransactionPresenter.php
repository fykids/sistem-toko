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
     *     stockCount: int,
     *     soldCount: int,
     *     recordedCapital: float|int|null,
     *     reportSoldCount: int,
     *     reportSoldRevenue: float|int,
     *     reportSoldCapital: float|int|null,
     *     reportSoldNetProfit: float|int|null,
     *     reportTransactionCount: int,
     *     reportStockCount: int,
     *     reportRecordedCapital: float|int|null,
     *     startDate: string|null,
     *     endDate: string|null,
     *     soldRevenue: float|int,
     *     soldCapital: float|int|null,
     *     soldNetProfit: float|int|null,
     *     tableColumnCount: int,
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
        $reportMetrics = $this->reportMetrics($filters, $canViewSensitiveData);
        $overallMetrics = $this->overallMetrics($canViewSensitiveData);
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
            'stockCount' => $overallMetrics->stock_count,
            'soldCount' => $overallMetrics->sold_count,
            'recordedCapital' => $canViewSensitiveData ? $overallMetrics->recorded_capital : null,
            'reportSoldCount' => $reportMetrics->sold_count,
            'reportSoldRevenue' => $reportMetrics->sold_revenue,
            'reportSoldCapital' => $canViewSensitiveData ? $reportMetrics->sold_capital : null,
            'reportSoldNetProfit' => $canViewSensitiveData ? $reportMetrics->sold_net_profit : null,
            'reportTransactionCount' => $reportMetrics->transaction_count,
            'reportStockCount' => $reportMetrics->stock_count,
            'reportRecordedCapital' => $canViewSensitiveData ? $reportMetrics->recorded_capital : null,
            'startDate' => $filters['start_date'] ?? null,
            'endDate' => $filters['end_date'] ?? null,
            'soldRevenue' => $overallMetrics->sold_revenue,
            'soldCapital' => $canViewSensitiveData ? $overallMetrics->sold_capital : null,
            'soldNetProfit' => $canViewSensitiveData ? $overallMetrics->sold_net_profit : null,
            'tableColumnCount' => $canViewSensitiveData ? 14 : 9,
            'canViewSensitiveData' => $canViewSensitiveData,
        ];
    }

    private function reportMetrics(array $filters, bool $canViewSensitiveData): CarTransaction
    {
        $query = CarTransaction::query()
            ->where(function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query->whereNull('sell_date')
                        ->when(
                            filled($filters['start_date'] ?? null),
                            fn ($query) => $query->whereDate('buy_date', '>=', $filters['start_date']),
                        )
                        ->when(
                            filled($filters['end_date'] ?? null),
                            fn ($query) => $query->whereDate('buy_date', '<=', $filters['end_date']),
                        );
                })->orWhere(function ($query) use ($filters): void {
                    $query->whereNotNull('sell_date')
                        ->when(
                            filled($filters['start_date'] ?? null),
                            fn ($query) => $query->whereDate('sell_date', '>=', $filters['start_date']),
                        )
                        ->when(
                            filled($filters['end_date'] ?? null),
                            fn ($query) => $query->whereDate('sell_date', '<=', $filters['end_date']),
                        );
                });
            });

        return $query
            ->selectRaw(implode(', ', $this->metricColumns($canViewSensitiveData)))
            ->firstOrFail();
    }

    private function overallMetrics(bool $canViewSensitiveData): CarTransaction
    {
        return CarTransaction::query()
            ->selectRaw(implode(', ', $this->metricColumns($canViewSensitiveData)))
            ->firstOrFail();
    }

    /**
     * @return array<int, string>
     */
    private function metricColumns(bool $canViewSensitiveData): array
    {
        $aggregateColumns = [
            'COUNT(*) as transaction_count',
            'COUNT(CASE WHEN sell_date IS NULL THEN 1 END) as stock_count',
            'COUNT(CASE WHEN sell_date IS NOT NULL THEN 1 END) as sold_count',
            'SUM(CASE WHEN sell_date IS NOT NULL THEN selling_price ELSE 0 END) as sold_revenue',
        ];

        if ($canViewSensitiveData) {
            $aggregateColumns[] = 'SUM(total_capital) as recorded_capital';
            $aggregateColumns[] = 'SUM(CASE WHEN sell_date IS NOT NULL THEN total_capital ELSE 0 END) as sold_capital';
            $aggregateColumns[] = 'SUM(CASE WHEN sell_date IS NOT NULL THEN net_profit ELSE 0 END) as sold_net_profit';
        }

        return $aggregateColumns;
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
