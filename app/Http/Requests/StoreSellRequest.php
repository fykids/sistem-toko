<?php

namespace App\Http\Requests;

use App\Models\SaleNote;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSellRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $amounts = [];

        foreach (['selling_price', 'down_payment', 'settlement_amount', 'leasing_disbursement'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $amounts[$field] = str_contains($value, ',')
                    ? str_replace(',', '.', str_replace('.', '', $value))
                    : (preg_match('/^\d{1,3}(?:\.\d{3})+$/D', $value) === 1
                        ? str_replace('.', '', $value)
                        : $value);
            }
        }

        if ($amounts !== []) {
            $this->merge($amounts);
        }
    }

    public function authorize(): bool
    {
        $buyer = $this->route('buyer');
        $ability = $buyer instanceof SaleNote ? 'update' : 'create';
        $model = $buyer instanceof SaleNote ? $buyer : SaleNote::class;

        return $this->user()?->can($ability, $model) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $buyer = $this->route('buyer');

        return [
            'car_transaction_id' => $buyer instanceof SaleNote
                ? ['prohibited']
                : [
                    'required',
                    'integer',
                    Rule::exists('car_transactions', 'id')->whereNull('sell_date'),
                    Rule::unique('sale_notes', 'car_transaction_id'),
                ],
            'nama_konsumen' => ['required', 'string', 'max:255'],
            'buyer_nik' => ['nullable', 'digits:16'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'buyer_address' => ['nullable', 'string', 'max:2000'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'down_payment' => ['required', 'numeric', 'min:0'],
            'down_payment_date' => ['nullable', 'date'],
            'settlement_amount' => ['required', 'numeric', 'min:0'],
            'settlement_date' => ['nullable', 'date'],
            'leasing_disbursement' => ['required', 'numeric', 'min:0'],
            'leasing_disbursement_date' => ['nullable', 'date'],
            'sell_date' => ['required', 'date'],
            'planned_payment_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $amountFields = [
                    'selling_price',
                    'down_payment',
                    'settlement_amount',
                    'leasing_disbursement',
                ];

                if ($validator->errors()->hasAny($amountFields)) {
                    return;
                }

                foreach ([
                    'down_payment' => 'down_payment_date',
                    'settlement_amount' => 'settlement_date',
                    'leasing_disbursement' => 'leasing_disbursement_date',
                ] as $amountField => $dateField) {
                    if ((float) $this->input($amountField) > 0 && blank($this->input($dateField))) {
                        $validator->errors()->add($dateField, 'Tanggal realisasi pembayaran wajib diisi.');
                    }
                }

                $remainingPayment = max(
                    (float) $this->input('selling_price')
                        - (float) $this->input('down_payment')
                        - (float) $this->input('settlement_amount')
                        - (float) $this->input('leasing_disbursement'),
                    0,
                );

                if ($remainingPayment > 0 && blank($this->input('planned_payment_date'))) {
                    $validator->errors()->add(
                        'planned_payment_date',
                        'Tanggal rencana pembayaran wajib diisi selama masih ada sisa pembayaran.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'car_transaction_id.required' => 'Pilih kendaraan yang akan dijual.',
            'car_transaction_id.exists' => 'Kendaraan yang dipilih tidak tersedia.',
            'car_transaction_id.unique' => 'Kendaraan ini sudah memiliki data Jual.',
            'car_transaction_id.prohibited' => 'Kendaraan pada transaksi Jual tidak dapat diganti.',
            'nama_konsumen.required' => 'Nama konsumen wajib diisi.',
            'buyer_nik.digits' => 'NIK harus terdiri dari 16 digit.',
            'buyer_phone.max' => 'Nomor HP maksimal 30 karakter.',
            'buyer_address.max' => 'Alamat maksimal 2.000 karakter.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'down_payment.required' => 'Nominal DP wajib diisi.',
            'down_payment.numeric' => 'Nominal DP harus berupa angka.',
            'down_payment.min' => 'Nominal DP tidak boleh kurang dari nol.',
            'down_payment_date.date' => 'Tanggal pembayaran DP harus berupa tanggal yang valid.',
            'settlement_amount.required' => 'Nominal pelunasan wajib diisi.',
            'settlement_amount.numeric' => 'Nominal pelunasan harus berupa angka.',
            'settlement_amount.min' => 'Nominal pelunasan tidak boleh kurang dari nol.',
            'settlement_date.date' => 'Tanggal pelunasan harus berupa tanggal yang valid.',
            'leasing_disbursement.required' => 'Nominal pencairan leasing wajib diisi.',
            'leasing_disbursement.numeric' => 'Nominal pencairan leasing harus berupa angka.',
            'leasing_disbursement.min' => 'Nominal pencairan leasing tidak boleh kurang dari nol.',
            'leasing_disbursement_date.date' => 'Tanggal pencairan leasing harus berupa tanggal yang valid.',
            'sell_date.required' => 'Tanggal penjualan wajib diisi.',
            'planned_payment_date.date' => 'Tanggal rencana pembayaran harus berupa tanggal yang valid.',
        ];
    }
}
