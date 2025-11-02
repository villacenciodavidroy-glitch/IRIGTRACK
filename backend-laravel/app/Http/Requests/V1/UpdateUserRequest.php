<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        // Get the user ID from route parameter
        $userId = $this->route('id') ?? $this->route('user');
        
        return [
            'fullname' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,' . $userId,
            'email' => 'sometimes|email|max:255|unique:users,email,' . $userId,
            'role' => 'sometimes|string|in:admin,user',
            'location_id' => 'sometimes|integer|exists:locations,id',
            'image' => 'nullable|sometimes|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'password' => 'sometimes|nullable|string|min:8|confirmed',
            'password_confirmation' => 'sometimes|nullable|required_with:password'
        ];
    }
}
