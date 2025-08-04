<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Will be handled by Sanctum middleware when needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resort_id' => ['required', 'integer', 'exists:resorts,id'],
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'rate_plan_id' => ['required', 'integer', 'exists:rate_plans,id'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'transfer_id' => ['nullable', 'integer', 'exists:transfers,id'],
            
            // Guest data
            'guest.email' => ['required', 'email', 'max:255'],
            'guest.full_name' => ['required', 'string', 'max:255'],
            'guest.phone' => ['required', 'string', 'max:20'],
            'guest.country' => ['required', 'string', 'size:2'], // ISO 2-letter country code
            
            // Additional guests (if any)
            'additional_guests' => ['nullable', 'array', 'max:9'], // Max 10 total including main guest
            'additional_guests.*.full_name' => ['required_with:additional_guests', 'string', 'max:255'],
            'additional_guests.*.age_category' => ['required_with:additional_guests', 'in:adult,child'],
            
            // Special requests
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'arrival_time' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'resort_id.exists' => 'The selected resort does not exist.',
            'room_type_id.exists' => 'The selected room type does not exist.',
            'rate_plan_id.exists' => 'The selected rate plan does not exist.',
            'transfer_id.exists' => 'The selected transfer does not exist.',
            'check_in.after_or_equal' => 'Check-in date must be today or later.',
            'check_out.after' => 'Check-out date must be after check-in date.',
            'guest.country.size' => 'Country must be a valid 2-letter ISO code.',
            'additional_guests.max' => 'Maximum 9 additional guests allowed.',
        ];
    }
}
