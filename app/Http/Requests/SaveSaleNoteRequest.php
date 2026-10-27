<?php

namespace App\Http\Requests;

use App\Models\SaleNote;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSaleNoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $paymentAmount = $this->input('payment_amount');
        $expenses = $this->input('intertine_expenses');

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
                Rule::exists('car_transactions', 'id')->whereNull('sell_date'),
                Rule::unique('sale_notes', 'car_transaction_id')->ignore($this->route('saleNote')),
            ],
            'nama_konsumen' => ['required', 'string', 'max:255'],
            'payment_type' => [
                'required',
                Rule::in(['selling_price', 'down_payment', 'settlement_amount', 'leasing_disbursement']),
            ],
            'payment_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'sell_date' => ['required', 'date'],
            'intertine_expenses' => ['required', 'array', 'size:3'],
            'intertine_expenses.*' => ['required', 'array:name,cost'],
            'intertine_expenses.*.name' => [
                'required',
                'distinct',
                Rule::in(['KR Jual', 'OP Survei', 'Fee CMO']),
            ],
            'intertine_expenses.*.cost' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ];
    }
}
