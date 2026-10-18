<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $leasingOptions = $this->input('leasing_options');

        if ($leasingOptions === null) {
            $this->merge(['leasing_options' => []]);
        } elseif (is_array($leasingOptions)) {
            $this->merge(['leasing_options' => array_values($leasingOptions)]);
        }

        foreach ([
            'additional_repairs' => ['name', 'date', 'cost', 'payment_status', 'payment_note'],
            'tax_items' => ['date', 'cost'],
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
            'mileage_km' => ['nullable', 'integer', 'min:0'],
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer'],
            'transmission' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'buy_date' => ['required', 'date'],
            'sell_date' => ['nullable', 'date'],
            'down_payment_date' => ['nullable', 'date'],
            'settlement_date' => ['nullable', 'date'],
            'leasing_disbursement_date' => ['nullable', 'date'],
            'buy_price' => ['required', 'numeric', 'min:0'],
            'purchase_operational' => ['nullable', 'numeric', 'min:0'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'settlement_amount' => ['nullable', 'numeric', 'min:0'],
            'leasing_disbursement' => ['nullable', 'numeric', 'min:0'],
            'setor_price' => ['nullable', 'numeric', 'min:0'],
            'opening_price' => ['nullable', 'numeric', 'min:0'],
            'leasing_ripan' => ['nullable', 'numeric', 'min:0'],
            'insurance_ripan' => ['nullable', 'numeric', 'min:0'],
            'booster_ripan' => ['nullable', 'numeric', 'min:0'],
            'additional_repairs' => ['nullable', 'array', 'max:50'],
            'additional_repairs.*' => ['array:name,date,cost,payment_status,payment_note'],
            'additional_repairs.*.name' => ['required', 'string', 'max:100'],
            'additional_repairs.*.date' => ['required', 'date'],
            'additional_repairs.*.cost' => ['required', 'numeric', 'min:0'],
            'additional_repairs.*.payment_status' => ['required', 'in:lunas,belum_lunas,panjar,lainnya'],
            'additional_repairs.*.payment_note' => ['nullable', 'string', 'max:255'],
            'tax_items' => ['nullable', 'array', 'max:50'],
            'tax_items.*' => ['array:name,date,cost,payment_status,payment_note'],
            'tax_items.*.name' => ['required', 'string', 'max:100'],
            'tax_items.*.date' => ['nullable', 'date'],
            'tax_items.*.cost' => ['required', 'numeric', 'min:0'],
            'tax_items.*.payment_status' => ['nullable', 'in:lunas,belum_lunas,panjar,lainnya'],
            'tax_items.*.payment_note' => ['nullable', 'string', 'max:255'],
            'intertine_expenses' => ['nullable', 'array', 'max:50'],
            'intertine_expenses.*' => ['array:name,cost'],
            'intertine_expenses.*.name' => ['required', 'string', 'max:100'],
            'intertine_expenses.*.cost' => ['required', 'numeric', 'min:0'],
            'leasing_options' => ['nullable', 'array', 'max:7'],
            'leasing_options.*' => ['array:name,disbursement,selling_price,dp,installment_3yr,installment_4yr,installment_5yr'],
            'leasing_options.*.name' => [
                'required',
                'string',
                'distinct:strict',
                Rule::in(['BCA', 'MUF', 'BUANA', 'ADIRA', 'SINAR MAS', 'WORI', 'WOM']),
            ],
            'leasing_options.*.disbursement' => ['nullable', 'numeric', 'min:0'],
            'leasing_options.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'leasing_options.*.dp' => ['nullable', 'numeric', 'min:0'],
            'leasing_options.*.installment_3yr' => ['nullable', 'numeric', 'min:0'],
            'leasing_options.*.installment_4yr' => ['nullable', 'numeric', 'min:0'],
            'leasing_options.*.installment_5yr' => ['nullable', 'numeric', 'min:0'],
            'vehicle_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
