<?php

namespace App\Http\Requests\WorkCenter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkCenterRequest extends FormRequest
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
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'price_per_operation' => ['nullable', 'numeric', 'min:0'],
            'employee_count'      => ['nullable', 'integer', 'min:0'],
        ];
    }
}
