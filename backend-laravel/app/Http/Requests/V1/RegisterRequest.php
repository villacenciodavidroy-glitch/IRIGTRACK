<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'fullname'     => 'required|string|max:255',
            'username'     => 'nullable|string|max:255|unique:users,username',
            'email'    => 'required|string|email|max:255|unique:users,email',
            'location_id' => 'required|exists:locations,id',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required_with:password|same:password',
            'role'     => 'nullable|in:admin,user,supply',
            'image' =>'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:5120'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'fullname.required' => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'username.unique' => 'This username is already taken.',
            'location_id.required' => 'Location is required.',
            'location_id.exists' => 'Selected location does not exist.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password_confirmation.required_with' => 'Please confirm your password.',
            'password_confirmation.same' => 'Password confirmation does not match.',
            'role.in' => 'Role must be either admin, user, or supply.',
            'image.image' => 'File must be an image.',
            'image.mimes' => 'Image must be jpeg, png, jpg, gif, or svg.',
            'image.max' => 'Image size must not exceed 5MB.',
        ];
    }
}
