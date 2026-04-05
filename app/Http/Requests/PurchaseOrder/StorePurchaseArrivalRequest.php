<?php

namespace App\Http\Requests\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseArrivalRequest extends FormRequest
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
            'arrival_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'lines.*.received_quantity' => ['nullable', 'integer', 'min:0'],
            'lines.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
