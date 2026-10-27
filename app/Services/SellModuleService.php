<?php

namespace App\Services;

use App\Models\CarTransaction;
use App\Models\SaleNote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

class SellModuleService
{
    public function listNotes(User $user, string $status = 'all'): LengthAwarePaginator
    {
        $canViewSensitiveData = $user->can('viewSensitiveData', CarTransaction::class);
        $query = SaleNote::query()
            ->with([
                'carTransaction' => fn ($query) => $query->select($this->vehicleColumns($canViewSensitiveData)),
                'payments' => fn ($query) => $query->orderBy('payment_date')->orderBy('id'),
            ])
            ->orderByDesc(
                CarTransaction::query()
                    ->select('sell_date')
                    ->whereColumn('car_transactions.id', 'sale_notes.car_transaction_id'),
            )
            ->orderByDesc('id');

        if (! $canViewSensitiveData) {
            $query->where('created_by', $user->id);
        }

        $query->when($status === 'paid', function ($query): void {
            $query->whereHas('carTransaction', fn ($vehicleQuery) => $vehicleQuery
                ->where('remaining_payment', '<=', 0));
        })->when($status === 'unpaid', function ($query): void {
            $query->whereHas('carTransaction', fn ($vehicleQuery) => $vehicleQuery
                ->where('remaining_payment', '>', 0)
                ->where('leasing_disbursement', '<=', 0))
                ->whereDoesntHave('payments', fn ($paymentQuery) => $paymentQuery
                    ->where('payment_method', 'leasing'));
        })->when($status === 'leasing', function ($query): void {
            $query->whereHas('carTransaction', fn ($vehicleQuery) => $vehicleQuery
                ->where('remaining_payment', '>', 0)
                ->where(function ($vehicleQuery): void {
                    $vehicleQuery->where('leasing_disbursement', '>', 0)
                        ->orWhereHas('saleNote.payments', fn ($paymentQuery) => $paymentQuery
                            ->where('payment_method', 'leasing'));
                }));
        });

        return $query->paginate(10)->withQueryString();
    }

    public function recordPayment(SaleNote $saleNote, array $attributes, User $recorder): void
    {
        DB::transaction(function () use ($saleNote, $attributes, $recorder): void {
            $lockedSaleNote = SaleNote::query()
                ->whereKey($saleNote->id)
                ->lockForUpdate()
                ->firstOrFail();
            $vehicle = CarTransaction::query()
                ->whereKey($lockedSaleNote->car_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $vehicle->remaining_payment <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Transaksi ini sudah lunas dan tidak memiliki sisa pembayaran.',
                ]);
            }

            $lockedSaleNote->payments()->create([
                'amount' => $attributes['amount'],
                'payment_method' => $attributes['payment_method'],
                'payment_date' => $attributes['payment_date'],
                'notes' => $attributes['notes'] ?? null,
                'recorded_by' => $recorder->id,
            ]);

            $settlementAmount = (float) $vehicle->settlement_amount + (float) $attributes['amount'];
            $remainingPayment = max(
                (float) $vehicle->selling_price
                    - (float) $vehicle->down_payment
                    - $settlementAmount
                    - (float) $vehicle->leasing_disbursement,
                0,
            );

            $vehicle->forceFill([
                'settlement_amount' => $settlementAmount,
                'settlement_date' => $attributes['payment_date'],
                'planned_payment_date' => $remainingPayment > 0
                    ? $vehicle->planned_payment_date
                    : null,
            ])->save();
        });
    }

    /**
     * @return LazyCollection<int, CarTransaction>
     */
    public function availableVehicles(bool $canViewSensitiveData): LazyCollection
    {
        return $this->availableVehiclesQuery()
            ->orderBy('plate_number')
            ->cursor($this->vehicleColumns($canViewSensitiveData));
    }

    public function hasAvailableVehicles(): bool
    {
        return $this->availableVehiclesQuery()->exists();
    }

    public function findAvailableVehicle(int $vehicleId, bool $canViewSensitiveData): ?CarTransaction
    {
        if ($vehicleId < 1) {
            return null;
        }

        return $this->availableVehiclesQuery()
            ->whereKey($vehicleId)
            ->first($this->vehicleColumns($canViewSensitiveData));
    }

    /**
     * @return Builder<CarTransaction>
     */
    private function availableVehiclesQuery(): Builder
    {
        return CarTransaction::query()
            ->whereNull('sell_date')
            ->whereDoesntHave('saleNote');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createNote(array $attributes, User $creator): SaleNote
    {
        $vehicleId = (int) $attributes['car_transaction_id'];

        return DB::transaction(function () use ($attributes, $creator, $vehicleId): SaleNote {
            $vehicle = CarTransaction::query()
                ->whereKey($vehicleId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $vehicle->sell_date !== null
                || $vehicle->saleNote()->exists()
            ) {
                throw ValidationException::withMessages([
                    'car_transaction_id' => 'Kendaraan ini sudah memiliki data Jual.',
                ]);
            }

            $vehicle->forceFill($this->vehicleSaleAttributes($vehicle, $attributes))->save();

            return $vehicle->saleNote()->create([
                'nama_konsumen' => $attributes['nama_konsumen'],
                'buyer_nik' => $attributes['buyer_nik'] ?? null,
                'buyer_phone' => $attributes['buyer_phone'] ?? null,
                'buyer_address' => $attributes['buyer_address'] ?? null,
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
            $vehicle = CarTransaction::query()
                ->whereKey($lockedSaleNote->car_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            $vehicle->forceFill($this->vehicleSaleAttributes($vehicle, $attributes))->save();
            $saleNoteAttributes = [
                'nama_konsumen' => $attributes['nama_konsumen'],
            ];

            foreach (['buyer_nik', 'buyer_phone', 'buyer_address'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    $saleNoteAttributes[$field] = $attributes[$field];
                }
            }

            $lockedSaleNote->update($saleNoteAttributes);

            return $lockedSaleNote;
        });
    }

    public function deleteNote(SaleNote $saleNote): void
    {
        DB::transaction(function () use ($saleNote): void {
            $lockedSaleNote = SaleNote::query()
                ->whereKey($saleNote->id)
                ->lockForUpdate()
                ->firstOrFail();
            $vehicle = CarTransaction::query()
                ->whereKey($lockedSaleNote->car_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            $vehicle->forceFill([
                'sell_date' => null,
                'selling_price' => 0,
            ])->save();
            $lockedSaleNote->delete();
        });
    }

    /**
     * @return array<int, string>
     */
    public function vehicleColumns(bool $canViewSensitiveData): array
    {
        $columns = [
            'id',
            'plate_number',
            'manager_name',
            'chassis_number',
            'engine_number',
            'mileage_km',
            'brand',
            'model',
            'year',
            'transmission',
            'color',
            'owner_name',
            'buy_date',
            'sell_date',
            'selling_price',
            'purchase_operational',
            'other_costs',
            'total_repair_cost',
            'additional_repairs',
            'tax_items',
            'setor_price',
            'opening_price',
            'down_payment',
            'down_payment_date',
            'settlement_amount',
            'settlement_date',
            'leasing_disbursement',
            'leasing_disbursement_date',
            'planned_payment_date',
            'remaining_payment',
            'intertine_expenses',
            'leasing_options',
        ];

        if ($canViewSensitiveData) {
            $columns = [
                ...$columns,
                'buy_price',
                'total_capital',
                'gross_profit',
                'showroom_fee',
                'total_intertine_expense',
                'net_profit',
                'leasing_ripan',
                'insurance_ripan',
                'booster_ripan',
            ];
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function vehicleSaleAttributes(CarTransaction $vehicle, array $attributes): array
    {
        if (array_key_exists('payment_type', $attributes)) {
            return $this->legacyVehicleSaleAttributes($vehicle, $attributes);
        }

        $sellingPrice = (float) $attributes['selling_price'];
        $downPayment = (float) $attributes['down_payment'];
        $settlementAmount = (float) $attributes['settlement_amount'];
        $leasingDisbursement = (float) $attributes['leasing_disbursement'];
        $remainingPayment = max(
            $sellingPrice - $downPayment - $settlementAmount - $leasingDisbursement,
            0,
        );

        return [
            'selling_price' => $sellingPrice,
            'down_payment' => $downPayment,
            'down_payment_date' => $attributes['down_payment_date'] ?? null,
            'settlement_amount' => $settlementAmount,
            'settlement_date' => $attributes['settlement_date'] ?? null,
            'leasing_disbursement' => $leasingDisbursement,
            'leasing_disbursement_date' => $attributes['leasing_disbursement_date'] ?? null,
            'planned_payment_date' => $remainingPayment > 0
                ? $attributes['planned_payment_date']
                : null,
            'sell_date' => $attributes['sell_date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function legacyVehicleSaleAttributes(CarTransaction $vehicle, array $attributes): array
    {
        $intertineExpenses = collect($vehicle->intertine_expenses ?? [])
            ->filter(fn (mixed $expense): bool => is_array($expense) && ! in_array(
                strtolower(trim((string) ($expense['name'] ?? ''))),
                ['kr jual', 'op survei', 'fee cmo'],
                true,
            ))
            ->values();

        foreach ($attributes['intertine_expenses'] ?? [] as $expense) {
            $intertineExpenses->push([
                'name' => $expense['name'],
                'cost' => $expense['cost'],
            ]);
        }

        $paymentAttributes = [];
        if (($attributes['payment_type'] ?? null) === 'selling_price') {
            $paymentAttributes['selling_price'] = $attributes['payment_amount'];
        } else {
            $paymentAttributes[$attributes['payment_type']] = $attributes['payment_amount'];
            $paymentDateField = [
                'down_payment' => 'down_payment_date',
                'leasing_disbursement' => 'leasing_disbursement_date',
                'settlement_amount' => 'settlement_date',
            ][$attributes['payment_type']];
            $paymentAttributes[$paymentDateField] = $attributes['payment_date'] ?? null;
        }

        return [
            ...$paymentAttributes,
            'sell_date' => $attributes['sell_date'],
            'intertine_expenses' => $intertineExpenses->all(),
        ];
    }
}
