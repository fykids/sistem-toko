<?php

namespace App\Services;

use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SellModuleService
{
    public function listNotes(bool $canViewSensitiveData): LengthAwarePaginator
    {
        return SaleNote::query()
            ->with([
                'carTransaction' => fn ($query) => $query->select($this->vehicleColumns($canViewSensitiveData)),
            ])
            ->orderByDesc('sell_date')
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function availableVehicles(bool $canViewSensitiveData): Collection
    {
        return CarTransaction::query()
            ->whereDoesntHave('saleNote')
            ->orderBy('plate_number')
            ->get($this->vehicleColumns($canViewSensitiveData));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createNote(array $attributes, User $creator): SaleNote
    {
        $vehicleId = (int) $attributes['car_transaction_id'];
        unset($attributes['car_transaction_id']);

        return DB::transaction(function () use ($attributes, $creator, $vehicleId): SaleNote {
            $vehicle = CarTransaction::query()
                ->whereKey($vehicleId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($vehicle->saleNote()->exists()) {
                throw ValidationException::withMessages([
                    'car_transaction_id' => 'Kendaraan ini sudah memiliki data Jual.',
                ]);
            }

            return $vehicle->saleNote()->create([
                ...$attributes,
                'created_by' => $creator->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateNote(SaleNote $saleNote, array $attributes): SaleNote
    {
        return DB::transaction(function () use ($attributes, $saleNote): SaleNote {
            $lockedSaleNote = SaleNote::query()
                ->whereKey($saleNote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSaleNote->update($attributes);

            return $lockedSaleNote;
        });
    }

    public function deleteNote(SaleNote $saleNote): void
    {
        DB::transaction(function () use ($saleNote): void {
            SaleNote::query()
                ->whereKey($saleNote->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->delete();
        });
    }

    /**
     * @return array<int, string>
     */
    public function vehicleColumns(bool $canViewSensitiveData): array
    {
        $columns = ['id', 'plate_number', 'brand', 'model', 'year', 'transmission', 'color'];

        if ($canViewSensitiveData) {
            $columns = [
                ...$columns,
                'buy_price',
                'total_capital',
                'setor_price',
                'leasing_ripan',
                'insurance_ripan',
                'booster_ripan',
            ];
        }

        return $columns;
    }
}
