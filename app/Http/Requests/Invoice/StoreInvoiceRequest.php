<?php

namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'customer_order_id' => ['nullable', 'integer', 'exists:customer_orders,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency_code' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.part_id' => ['nullable', 'integer', 'exists:parts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
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
