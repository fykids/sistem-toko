<?php

namespace App\Http\Requests;

use App\Models\SaleNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSaleNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $saleNote = $this->route('saleNote');

        return $this->user()?->can(
            $saleNote instanceof SaleNote ? 'update' : 'create',
            $saleNote instanceof SaleNote ? $saleNote : SaleNote::class,
        ) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'car_transaction_id' => [
                $this->isMethod('post') ? 'required' : 'prohibited',
                'integer',
                Rule::exists('car_transactions', 'id'),
                Rule::unique('sale_notes', 'car_transaction_id')->ignore($this->route('saleNote')),
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
}
