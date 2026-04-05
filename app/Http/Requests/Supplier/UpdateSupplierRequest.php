<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
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
        $supplierId = $this->route('supplier')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplierId)],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ];
    }
}
