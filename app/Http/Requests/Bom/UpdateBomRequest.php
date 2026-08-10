<?php

namespace App\Http\Requests\Bom;

use App\Models\Bom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBomRequest extends FormRequest
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
            'part_id'           => ['required', 'integer', 'exists:parts,id'],
            'name'              => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'is_active'         => ['boolean'],
            'planning_strategy' => ['required', Rule::in([Bom::PLANNING_STRATEGY_ORDER_ORIENTED, Bom::PLANNING_STRATEGY_STOCK_DRIVEN])],
            'items'             => ['nullable', 'array'],
            'items.*.line_type'          => ['required_with:items', 'in:part,operation'],
            'items.*.component_part_id'  => ['nullable', 'integer', 'exists:parts,id'],
            'items.*.work_center_id'     => ['nullable', 'integer', 'exists:work_centers,id'],
            'items.*.quantity'           => ['required_with:items', 'numeric', 'min:0.0001'],
            'items.*.notes'              => ['nullable', 'string', 'max:500'],
            'items.*.sort_order'         => ['nullable', 'integer', 'min:0'],
        ];
    }
}
