<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GuestProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authentication handled by Sanctum middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $guestProfileId = $this->route('guest_profile');
        
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:guest_profiles,email,' . $guestProfileId
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'country' => ['required', 'string', 'size:2'], // ISO 2-letter country code
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'size:2'], // ISO 2-letter country code
            'preferences' => ['nullable', 'array'],
            'preferences.dietary_requirements' => ['nullable', 'string', 'max:500'],
            'preferences.accessibility_needs' => ['nullable', 'string', 'max:500'],
            'preferences.room_preferences' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already registered.',
            'country.size' => 'Country must be a valid 2-letter ISO code.',
            'nationality.size' => 'Nationality must be a valid 2-letter ISO code.',
            'date_of_birth.before' => 'Date of birth must be in the past.',
        ];
    }
}
