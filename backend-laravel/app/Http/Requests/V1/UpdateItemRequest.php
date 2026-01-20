<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
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
        $item = $this->route('item'); // Get the item from route (Item model instance)
        
        return [
            'unit' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'serial_number' => 'sometimes|nullable|string|max:255|unique:items,serial_number,' . ($item ? $item->uuid : '') . ',uuid',
            'model' => 'sometimes|string|max:255',
            'pac' => 'sometimes|string|max:50',
            'unit_value' => 'sometimes|numeric|min:0',
            'quantity' => 'sometimes|integer|min:1',
            'date_acquired' => 'sometimes|date|before_or_equal:today',
            'po_number' => 'sometimes|string|max:50',
            'category_id' => 'sometimes|exists:categories,id',
            'location_id' => 'sometimes|nullable|exists:locations,id',
            'condition_id' => 'sometimes|exists:conditions,id',
            'condition_number_id' => 'sometimes|exists:condition_numbers,id',
            'user_id' => 'sometimes|nullable|exists:users,id',
            'maintenance_reason' => 'nullable|string|max:255',
            'technician_notes' => 'nullable|string|max:1000',
            'image_path' =>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240'
        ];
    }
}
