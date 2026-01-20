<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
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
        return [
            'unit' => 'required|string|max:255',
            // 'issued_to' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'serial_number' => 'nullable|string|max:255|unique:items,serial_number',
            'model' => 'required|string|max:255',
            'pac' => 'required|string|max:50',
            'unit_value' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'date_acquired' => 'required|date|before_or_equal:today',
            'po_number' => 'required|string|max:50',
            'category_id' => 'required|exists:categories,id',
            'location_id' => 'required|exists:locations,id',
            'condition_id' => 'nullable',
            'condition_number_id' => 'nullable',
            'user_id' => 'nullable|exists:users,id',
            'maintenance_reason' => 'nullable|string|max:255',
            'technician_notes' => 'nullable|string|max:1000',
            'image_path' =>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:10240'
        ];
    }
}
