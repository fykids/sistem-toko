<?php

namespace App\Http\Requests;

use App\Models\CarTransaction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCarTransactionRequest extends FormRequest
{
    private const MAX_DECIMAL_AMOUNT = '9999999999999.99';

    private const NON_NEGATIVE_AMOUNT_RULES = [
        'numeric',
        'min:0',
        'max:9999999999999.99',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        $transaction = $this->route('transaction');

        if (! $user instanceof User) {
            return false;
        }

        if ($transaction instanceof CarTransaction) {
            return $user->can('update', $transaction);
        }

        return $user->can('create', CarTransaction::class);
    }

    protected function prepareForValidation(): void
    {
        $leasingOptions = $this->input('leasing_options');

        if ($leasingOptions === null) {
            $this->merge(['leasing_options' => []]);
        } elseif (is_array($leasingOptions)) {
            $leasingOptions = array_filter(
                $leasingOptions,
                fn ($option): bool => ! is_array($option)
                    || collect($option)->contains(fn ($value): bool => filled($value)),
            );
            $this->merge(['leasing_options' => array_values($leasingOptions)]);
        }

        foreach ([
            'additional_repairs' => ['name', 'date', 'cost', 'payment_status', 'payment_note'],
            'tax_items' => ['date', 'cost', 'estimated_cost'],
            'intertine_expenses' => ['name', 'cost'],
        ] as $field => $meaningfulKeys) {
            $items = $this->input($field);

            if ($items === null) {
                $this->merge([$field => []]);

                continue;
            }

            if (is_array($items)) {
                if ($field === 'intertine_expenses') {
                    foreach ($items as &$item) {
                        if (is_array($item)) {
                            unset($item['date']);
                        }
                    }

                    unset($item);
                }

                $this->merge([
                    $field => array_filter($items, static function ($item) use ($field, $meaningfulKeys): bool {
                        if (! is_array($item)) {
                            return true;
                        }

                        if ($field === 'tax_items' && strtolower($item['name'] ?? '') === 'pajak') {
                            return true;
                        }

                        foreach ($meaningfulKeys as $key) {
                            if (filled($item[$key] ?? null)) {
                                return true;
                            }
                        }

                        return false;
                    }),
                ]);
            }
        }

        foreach (['additional_repairs', 'tax_items', 'intertine_expenses'] as $field) {
            $items = $this->input($field);

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as &$item) {
                if (is_array($item) && (($item['cost'] ?? null) === null || $item['cost'] === '')) {
                    $item['cost'] = 0;
                }
            }

            unset($item);

            $this->merge([$field => $items]);
        }

        $numericValues = [];

        foreach ([
            'mileage_km',
            'year',
            'buy_price',
            'purchase_operational',
            'other_costs',
            'selling_price',
            'down_payment',
            'settlement_amount',
            'leasing_disbursement',
            'setor_price',
            'opening_price',
            'leasing_ripan',
            'insurance_ripan',
            'booster_ripan',
        ] as $field) {
            $value = $this->input($field);

            $numericValues[$field] = $value === null || $value === '' ? 0 : $value;
        }

        $this->merge($numericValues);
    }

    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'chassis_number' => ['nullable', 'string', 'max:255'],
            'engine_number' => ['nullable', 'string', 'max:255'],
            'mileage_km' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => [
                'required',
                'integer',
                'min:0',
                'max:2155',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if ((int) $value !== 0 && (int) $value < 1901) {
                        $fail('Tahun harus 0 atau berada di antara 1901 dan 2155.');
                    }
                },
            ],
            'transmission' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'nama_penjual' => ['required', 'string', 'max:255'],
            'nik_penjual' => ['nullable', 'digits:16'],
            'no_hp_penjual' => ['nullable', 'string', 'max:30'],
            'alamat_penjual' => ['nullable', 'string', 'max:5000'],
            'nama_broker' => ['nullable', 'string', 'max:255'],
            'buy_date' => ['required', 'date'],
            'sell_date' => ['nullable', 'date'],
            'down_payment_date' => ['nullable', 'date'],
            'settlement_date' => ['nullable', 'date'],
            'leasing_disbursement_date' => ['nullable', 'date'],
            'planned_payment_date' => ['nullable', 'date'],
            'buy_price' => ['required', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'purchase_operational' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'other_costs' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'selling_price' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'down_payment' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'settlement_amount' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'leasing_disbursement' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'setor_price' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'opening_price' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'leasing_ripan' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'insurance_ripan' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'booster_ripan' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'additional_repairs' => ['nullable', 'array', 'max:50'],
            'additional_repairs.*' => ['array:name,date,cost,payment_status,payment_note'],
            'additional_repairs.*.name' => ['required', 'string', 'max:100'],
            'additional_repairs.*.date' => ['required', 'date'],
            'additional_repairs.*.cost' => ['required', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'additional_repairs.*.payment_status' => ['required', 'in:lunas,belum_lunas,panjar,lainnya'],
            'additional_repairs.*.payment_note' => ['nullable', 'string', 'max:255'],
            'tax_items' => ['nullable', 'array', 'max:50'],
            'tax_items.*' => ['array:name,date,cost,estimated_cost,payment_status,payment_note'],
            'tax_items.*.name' => ['required', 'string', 'max:100'],
            'tax_items.*.date' => ['nullable', 'date'],
            'tax_items.*.cost' => ['required', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'tax_items.*.estimated_cost' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'tax_items.*.payment_status' => ['nullable', 'in:lunas,belum_lunas,panjar,lainnya'],
            'tax_items.*.payment_note' => ['nullable', 'string', 'max:255'],
            'intertine_expenses' => ['nullable', 'array', 'max:50'],
            'intertine_expenses.*' => ['array:name,cost'],
            'intertine_expenses.*.name' => ['required', 'string', 'max:100'],
            'intertine_expenses.*.cost' => ['required', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'leasing_options' => ['nullable', 'array', 'max:50'],
            'leasing_options.*' => ['array:name,disbursement,selling_price,dp,installment_3yr,installment_4yr,installment_5yr'],
            'leasing_options.*.name' => [
                'required',
                'string',
                'max:100',
                'distinct:strict',
            ],
            'leasing_options.*.disbursement' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'leasing_options.*.selling_price' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'leasing_options.*.dp' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'leasing_options.*.installment_3yr' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'leasing_options.*.installment_4yr' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'leasing_options.*.installment_5yr' => ['nullable', ...self::NON_NEGATIVE_AMOUNT_RULES],
            'foto_kendaraan' => ['nullable', 'array', 'max:20'],
            'foto_kendaraan.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'foto_utama' => ['nullable', 'string', 'max:40'],
            'hapus_foto' => ['nullable', 'array', 'max:20'],
            'hapus_foto.*' => ['required', 'integer', 'distinct'],
            'hapus_foto_legacy' => ['nullable', 'boolean'],
            'vehicle_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $photoFiles = $this->file('foto_kendaraan', []);
                $photoFiles = is_array($photoFiles) ? array_values($photoFiles) : [];
                $legacyPhoto = $this->file('vehicle_photo');

                if ($legacyPhoto !== null) {
                    $photoFiles[] = $legacyPhoto;
                }

                if (count($photoFiles) > 20) {
                    $validator->errors()->add('foto_kendaraan', 'Maksimal 20 foto kendaraan dapat diunggah.');
                }

                $transaction = $this->route('transaction');
                $existingPhotos = $transaction instanceof CarTransaction
                    ? $transaction->vehiclePhotos()->get(['id', 'path'])
                    : collect();
                $existingPhotoIds = $existingPhotos->pluck('id')
                    ->map(static fn ($id): string => (string) $id)
                    ->all();
                $removedPhotoIds = collect($this->input('hapus_foto', []))
                    ->map(static fn ($id): string => (string) $id)
                    ->all();
                if (array_diff($removedPhotoIds, $existingPhotoIds) !== []) {
                    $validator->errors()->add('hapus_foto', 'Salah satu foto yang akan dihapus tidak tersedia.');

                    return;
                }

                $availablePhotoIds = array_diff($existingPhotoIds, $removedPhotoIds);
                $coverToken = $this->input('foto_utama');
                $isLegacyCoverAvailable = $transaction instanceof CarTransaction
                    && $transaction->vehicle_photo_path !== null
                    && ! $existingPhotos->contains('path', $transaction->vehicle_photo_path)
                    && ! $this->boolean('hapus_foto_legacy');
                $remainingPhotoCount = count($existingPhotoIds)
                    - count(array_intersect($existingPhotoIds, $removedPhotoIds))
                    + ($isLegacyCoverAvailable ? 1 : 0)
                    + count($photoFiles);

                if ($this->boolean('hapus_foto_legacy') && $transaction instanceof CarTransaction) {
                    $legacyPhoto = $existingPhotos->firstWhere('path', $transaction->vehicle_photo_path);

                    if ($legacyPhoto !== null && ! in_array((string) $legacyPhoto->id, $removedPhotoIds, true)) {
                        $remainingPhotoCount--;
                    }
                }

                if ($remainingPhotoCount > 20) {
                    $validator->errors()->add('foto_kendaraan', 'Maksimal 20 foto kendaraan dapat disimpan.');

                    return;
                }

                if (
                    filled($coverToken)
                    && ! (
                        preg_match('/^new:(\d+)$/D', $coverToken, $matches) === 1
                        && array_key_exists((int) $matches[1], $photoFiles)
                    )
                    && ! (
                        preg_match('/^existing:(\d+)$/D', $coverToken, $matches) === 1
                        && in_array($matches[1], $availablePhotoIds, true)
                    )
                    && ! ($coverToken === 'existing:legacy' && $isLegacyCoverAvailable)
                ) {
                    $validator->errors()->add('foto_utama', 'Pilih salah satu foto yang masih tersedia sebagai foto utama.');
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $paymentFields = [
                    'selling_price',
                    'down_payment',
                    'settlement_amount',
                    'leasing_disbursement',
                ];

                if ($validator->errors()->hasAny($paymentFields)) {
                    return;
                }

                $remainingPayment = max(
                    (float) $this->input('selling_price', 0)
                        - (float) $this->input('down_payment', 0)
                        - (float) $this->input('settlement_amount', 0)
                        - (float) $this->input('leasing_disbursement', 0),
                    0,
                );

                if ($remainingPayment > 0 && ! filled($this->input('planned_payment_date'))) {
                    $validator->errors()->add(
                        'planned_payment_date',
                        'Tanggal rencana pembayaran wajib diisi jika masih terdapat sisa pembayaran.',
                    );
                }

                $repairCost = $this->sumItemCosts('additional_repairs');
                $taxCost = $this->sumItemCosts('tax_items');
                $intertineCost = $this->sumItemCosts('intertine_expenses');
                $totalCapital = (float) $this->input('buy_price', 0)
                    + (float) $this->input('purchase_operational', 0)
                    + (float) $this->input('other_costs', 0)
                    + $taxCost
                    + $repairCost;
                $grossProfit = (float) $this->input('selling_price', 0) - $totalCapital;
                $totalIntertineExpense = ($grossProfit * 0.10) + $intertineCost;
                $netProfit = $grossProfit - $totalIntertineExpense;

                foreach ([
                    'additional_repairs' => $repairCost,
                    'buy_price' => $totalCapital,
                    'intertine_expenses' => $totalIntertineExpense,
                    'selling_price' => $netProfit,
                ] as $field => $calculatedAmount) {
                    if (abs($calculatedAmount) > (float) self::MAX_DECIMAL_AMOUNT) {
                        $validator->errors()->add(
                            $field,
                            'Total nilai gabungan melebihi batas maksimum nominal yang dapat disimpan.',
                        );
                    }
                }
            },
        ];
    }

    private function sumItemCosts(string $field): float
    {
        return array_sum(array_map(
            static fn (array $item): float => (float) ($item['cost'] ?? 0),
            $this->input($field, []),
        ));
    }
}
