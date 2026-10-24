<?php

namespace App\Http\Requests;

use App\Models\SaleNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSellRequest extends FormRequest
{
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
                    Rule::exists('car_transactions', 'id'),
                    Rule::unique('sale_notes', 'car_transaction_id'),
                ],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_nik' => ['nullable', 'digits:16'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'buyer_address' => ['nullable', 'string', 'max:5000'],
            'deal_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'payment_method' => ['required', 'string', Rule::in(['Tunai', 'Transfer', 'Kredit', 'Lainnya'])],
            'sell_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
            'buyer_name.required' => 'Nama pembeli wajib diisi.',
            'buyer_nik.digits' => 'NIK harus terdiri dari 16 angka.',
            'deal_price.required' => 'Harga deal wajib diisi.',
            'deal_price.min' => 'Harga deal tidak boleh kurang dari nol.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'sell_date.required' => 'Tanggal penjualan wajib diisi.',
        ];
    }
}
