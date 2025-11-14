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
            'fullname' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $userId,
            'email' => 'required|email|max:255|unique:users,email,' . $userId,
            'role' => 'required|string|in:admin,user,supply',
            'location_id' => 'nullable|integer|exists:locations,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:10240',
            'password' => 'nullable|string|min:8|confirmed',
            'password_confirmation' => 'nullable|required_with:password|same:password'
        ];
    }
    
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // For PUT requests with FormData, Laravel may not parse it correctly
        // Try to get data from the request bag directly
        $input = $this->all();
        
        // If input is empty but we have a multipart request, try to get data from request bag
        if (empty($input) && $this->header('Content-Type') && str_contains($this->header('Content-Type'), 'multipart/form-data')) {
            // Try to access the underlying Symfony request to get FormData
            $symfonyRequest = $this->instance ?? request()->instance();
            if ($symfonyRequest) {
                $requestBag = $symfonyRequest->request->all();
                $filesBag = $symfonyRequest->files->all();
                
                \Log::info('UpdateUserRequest: FormData detected, trying request bag', [
                    'request_bag' => $requestBag,
                    'files_bag_keys' => array_keys($filesBag)
                ]);
                
                // Merge request bag data into the request
                if (!empty($requestBag)) {
                    foreach ($requestBag as $key => $value) {
                        $this->merge([$key => $value]);
                    }
                }
                
                // Merge files
                if (!empty($filesBag)) {
                    foreach ($filesBag as $key => $file) {
                        $this->files->set($key, $file);
                    }
                }
                
                // Update input after merge
                $input = $this->all();
            }
        }
        
        // Log what we receive before processing
        \Log::info('UpdateUserRequest prepareForValidation', [
            'input_keys' => array_keys($input),
            'input_values' => $input,
            'fullname' => $input['fullname'] ?? 'NOT SET',
            'username' => $input['username'] ?? 'NOT SET',
            'email' => $input['email'] ?? 'NOT SET',
            'role' => $input['role'] ?? 'NOT SET',
            'location_id' => $input['location_id'] ?? 'NOT SET',
        ]);
        
        // Handle location_id - convert empty/null to null (it's nullable)
        if (isset($input['location_id'])) {
            if ($input['location_id'] === '' || $input['location_id'] === null) {
                $this->merge(['location_id' => null]);
            }
        }
    }
    
    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'fullname.required' => 'Full name is required.',
            'username.required' => 'Username is required.',
            'username.unique' => 'This username is already taken.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already taken.',
            'location_id.exists' => 'Selected location does not exist.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, svg, webp.',
            'image.max' => 'The image must not be larger than 10MB.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password_confirmation.same' => 'Password confirmation does not match.',
        ];
    }
}
