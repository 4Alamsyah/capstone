<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'currency_code' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('currency_code') === '') {
            $this->merge([
                'currency_code' => null,
            ]);
        }
    }
}
