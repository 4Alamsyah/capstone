<?php

namespace App\Http\Requests\Part;

use Illuminate\Foundation\Http\FormRequest;

class StorePartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'part_number' => ['required', 'string', 'max:100', 'unique:parts,part_number'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:purchase,manufacture'],
            'description' => ['nullable', 'string'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'safety_stock' => ['required', 'integer', 'min:0'],
            'suppliers' => ['required', 'array', 'min:1'],
            'suppliers.*.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'suppliers.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'stocks' => ['nullable', 'array'],
            'stocks.*.warehouse_id' => ['required_with:stocks', 'integer', 'exists:warehouses,id'],
            'stocks.*.quantity' => ['required_with:stocks', 'integer', 'min:0'],
        ];
    }
}
