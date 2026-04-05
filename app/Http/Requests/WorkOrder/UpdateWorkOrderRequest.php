<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
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
            'status'         => ['required', Rule::in(['draft', 'released', 'in_progress', 'completed', 'cancelled'])],
            'quantity'       => ['required', 'numeric', 'min:0.0001'],
            'scheduled_date' => ['nullable', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
