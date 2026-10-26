<?php

namespace App\Http\Requests;

use App\Models\SaleNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $sellingPrice = $this->input('selling_price');
        $paymentAmount = $this->input('payment_amount');
        $expenses = $this->input('intertine_expenses');

        if (is_string($sellingPrice)) {
            $this->merge([
                'selling_price' => str_replace(',', '.', str_replace('.', '', $sellingPrice)),
            ]);
        }

        if (is_string($paymentAmount)) {
            $this->merge([
                'payment_amount' => str_replace(',', '.', str_replace('.', '', $paymentAmount)),
            ]);
        }

        if (is_array($expenses)) {
            foreach ($expenses as &$expense) {
                if (is_array($expense) && is_string($expense['cost'] ?? null)) {
                    $expense['cost'] = str_replace(',', '.', str_replace('.', '', $expense['cost']));
                }
            }

            unset($expense);
            $this->merge(['intertine_expenses' => $expenses]);
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
            'selling_price' => ['required', 'numeric', 'min:0'],
            'payment_type' => [
                'required',
                Rule::in(['down_payment', 'settlement_amount', 'leasing_disbursement']),
            ],
            'payment_amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['nullable', 'date'],
            'sell_date' => ['required', 'date'],
            'intertine_expenses' => ['required', 'array', 'size:3'],
            'intertine_expenses.*' => ['required', 'array:name,cost'],
            'intertine_expenses.*.name' => [
                'required',
                'distinct',
                Rule::in(['KR Jual', 'OP Survei', 'Fee CMO']),
            ],
            'intertine_expenses.*.cost' => ['required', 'numeric', 'min:0'],
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
            'selling_price.required' => 'Harga jual wajib diisi.',
            'payment_type.required' => 'Pilih kategori pembayaran.',
            'payment_amount.required' => 'Nilai pembayaran wajib diisi.',
            'payment_amount.numeric' => 'Nilai pembayaran harus berupa angka.',
            'payment_amount.min' => 'Nilai pembayaran tidak boleh kurang dari nol.',
            'payment_date.date' => 'Tanggal pembayaran harus berupa tanggal yang valid.',
            'sell_date.required' => 'Tanggal penjualan wajib diisi.',
        ];
    }
}
