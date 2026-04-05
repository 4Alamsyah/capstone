<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportWorkOrderRequest extends FormRequest
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
            'new_status' => ['required', Rule::in(['draft', 'released', 'in_progress', 'completed', 'cancelled'])],
            'good_quantity' => ['required', 'numeric', 'min:0'],
            'reject_quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'consumptions' => ['nullable', 'array'],
            'consumptions.*.bom_item_id' => ['required_with:consumptions', 'integer', 'exists:bom_items,id'],
            'consumptions.*.part_id' => ['required_with:consumptions', 'integer', 'exists:parts,id'],
            'consumptions.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'consumptions.*.quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
