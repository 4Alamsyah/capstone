<?php

namespace App\Http\Requests\PurchaseVoucher;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertToPurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'   => ['required', 'integer', 'exists:suppliers,id'],
            'order_date'    => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency_code' => ['nullable', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'notes'         => ['nullable', 'string', 'max:5000'],
            'lines'         => ['required', 'array', 'min:1'],
            'lines.*.purchase_voucher_item_id' => ['required', 'integer', 'exists:purchase_voucher_items,id'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.remarks'    => ['nullable', 'string', 'max:1000'],
        ];
    }
}
