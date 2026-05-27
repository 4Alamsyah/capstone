<?php

namespace App\Http\Requests\PurchaseVoucher;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source'        => ['required', 'string', 'in:manual,stock_recommendation'],
            'required_date' => ['nullable', 'date'],
            'notes'         => ['nullable', 'string', 'max:5000'],
            'lines'         => ['required', 'array', 'min:1'],
            'lines.*.part_id'  => ['required', 'integer', 'exists:parts,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit'     => ['nullable', 'string', 'max:20'],
            'lines.*.remarks'  => ['nullable', 'string', 'max:1000'],
        ];
    }
}
