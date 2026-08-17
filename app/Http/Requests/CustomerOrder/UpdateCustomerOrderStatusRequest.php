<?php

namespace App\Http\Requests\CustomerOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerOrderStatusRequest extends FormRequest
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
            'status' => ['required', 'integer', 'in:4,9'],
        ];
    }
}
