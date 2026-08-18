<?php

namespace App\Http\Requests\Uom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUomRequest extends FormRequest
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
        $uomId = $this->route('uom')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('uoms', 'code')->ignore($uomId)],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
