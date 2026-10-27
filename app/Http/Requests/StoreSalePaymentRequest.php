<?php

namespace App\Http\Requests;

use App\Models\SaleNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $saleNote = $this->route('buyer');

        return $saleNote instanceof SaleNote
            && ($this->user()?->can('recordPayment', $saleNote) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        if (is_string($amount)) {
            $amount = str_contains($amount, ',')
                ? str_replace(',', '.', str_replace('.', '', $amount))
                : (preg_match('/^\d{1,3}(?:\.\d{3})+$/', $amount) === 1
                    ? str_replace('.', '', $amount)
                    : $amount);

            $this->merge([
                'amount' => $amount,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'leasing', 'bank_transfer'])],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal pelunasan wajib diisi.',
            'amount.numeric' => 'Nominal pelunasan harus berupa angka.',
            'amount.gt' => 'Nominal pelunasan harus lebih besar dari nol.',
            'payment_method.required' => 'Pilih metode pelunasan.',
            'payment_method.in' => 'Metode pelunasan yang dipilih tidak valid.',
            'payment_date.required' => 'Tanggal pelunasan wajib diisi.',
            'payment_date.date' => 'Tanggal pelunasan tidak valid.',
            'notes.max' => 'Catatan pelunasan maksimal 1000 karakter.',
        ];
    }
}
