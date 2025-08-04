<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'country' => $this->country,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'passport_number' => $this->when($this->passport_number, $this->passport_number),
            'nationality' => $this->when($this->nationality, $this->nationality),
            'preferences' => $this->when($this->preferences, $this->preferences), // JSON field
            'total_bookings' => $this->whenCounted('bookings'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
