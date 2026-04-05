<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
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
            'bom_id'         => ['required', 'integer', 'exists:boms,id'],
            'quantity'       => ['required', 'numeric', 'min:0.0001'],
            'scheduled_date' => ['nullable', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }
}
