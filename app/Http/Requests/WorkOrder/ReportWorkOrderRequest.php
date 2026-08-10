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
            'components' => ['nullable', 'array'],
            'components.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'components.*.quantity' => ['nullable', 'integer', 'min:0'],
            'components.*.good_quantity' => ['nullable', 'integer', 'min:0'],
            'components.*.reject_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
