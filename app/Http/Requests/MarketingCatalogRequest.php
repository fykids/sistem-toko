<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class MarketingCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->role === 'marketing';
    }

    public function rules(): array
    {
        $yearToRules = ['nullable', 'integer', 'between:1900,2100'];

        if ($this->filled('year_from')) {
            $yearToRules[] = 'gte:year_from';
        }

        return [
            'q' => ['nullable', 'string', 'max:255'],
            'transmission' => ['nullable', 'in:manual,otomatis,automatic'],
            'year_from' => ['nullable', 'integer', 'between:1900,2100'],
            'year_to' => $yearToRules,
        ];
    }
}
