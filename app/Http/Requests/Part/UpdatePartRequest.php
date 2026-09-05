<?php

namespace App\Http\Requests\Part;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartRequest extends FormRequest
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
        $partId = $this->route('part')?->id;

        return [
            'part_number' => ['required', 'string', 'max:100', Rule::unique('parts', 'part_number')->ignore($partId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:purchase,manufacture'],
            'inventory_type' => ['required', 'string', 'in:material,tool'],
            'default_uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'default_warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'description' => ['nullable', 'string'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'safety_stock' => ['required', 'integer', 'min:0'],
            'suppliers' => [Rule::requiredIf($this->input('category') === 'purchase'), 'array'],
            'suppliers.*.supplier_id' => ['required_with:suppliers', 'integer', 'exists:suppliers,id'],
            'suppliers.*.purchase_price' => ['required_with:suppliers', 'numeric', 'min:0'],
            'stocks' => ['nullable', 'array'],
            'stocks.*.warehouse_id' => ['required_with:stocks', 'integer', 'exists:warehouses,id'],
            'stocks.*.quantity' => ['required_with:stocks', 'integer', 'min:0'],
        ];
    }
}
