<?php

namespace App\Services;

use App\Models\CarTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MarketingCatalogService
{
    /**
     * @param  array{q?: string|null, transmission?: string|null, year_from?: int|string|null, year_to?: int|string|null}  $filters
     */
    public function stock(array $filters): LengthAwarePaginator
    {
        $search = trim($filters['q'] ?? '');
        $transmission = mb_strtolower((string) ($filters['transmission'] ?? ''));

        return CarTransaction::query()
            ->whereNull('sell_date')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $searchPattern = '%'.$search.'%';
                $query->where(function (Builder $query) use ($searchPattern): void {
                    $query->where('plate_number', 'like', $searchPattern)
                        ->orWhere('brand', 'like', $searchPattern)
                        ->orWhere('model', 'like', $searchPattern);
                });
            })
            ->when(
                $transmission !== '',
                function (Builder $query) use ($transmission): void {
                    if ($transmission === 'manual') {
                        $query->whereRaw('LOWER(transmission) = ?', ['manual']);

                        return;
                    }

                    $query->whereRaw('LOWER(transmission) IN (?, ?)', ['otomatis', 'automatic']);
                },
            )
            ->when(
                filled($filters['year_from'] ?? null),
                fn (Builder $query) => $query->where('year', '>=', $filters['year_from']),
            )
            ->when(
                filled($filters['year_to'] ?? null),
                fn (Builder $query) => $query->where('year', '<=', $filters['year_to']),
            )
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->paginate(10, [
                'id',
                'plate_number',
                'brand',
                'model',
                'year',
                'transmission',
                'color',
                'opening_price',
                'vehicle_photo_path',
                'tax_items',
            ])
            ->withQueryString();
    }

    public function availableVehicle(int $vehicleId): CarTransaction
    {
        return CarTransaction::query()
            ->whereNull('sell_date')
            ->whereKey($vehicleId)
            ->firstOrFail([
                'id',
                'plate_number',
                'brand',
                'model',
                'year',
                'transmission',
                'color',
                'buy_date',
                'chassis_number',
                'engine_number',
                'mileage_km',
                'owner_name',
                'opening_price',
                'vehicle_photo_path',
                'tax_items',
                'leasing_options',
            ]);
    }
}
