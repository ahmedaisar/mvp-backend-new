<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'room_type_id' => $this->room_type_id,
            'room_type' => new RoomTypeResource($this->whenLoaded('roomType')),
            'rate_plan_id' => $this->rate_plan_id,
            'rate_plan' => [
                'id' => $this->ratePlan->id,
                'name' => $this->ratePlan->name,
                'refundable' => $this->ratePlan->refundable,
                'breakfast_included' => $this->ratePlan->breakfast_included,
                'cancellation_policy' => $this->ratePlan->cancellation_policy,
                'deposit_required' => $this->ratePlan->deposit_required,
                'min_stay' => $this->ratePlan->min_stay,
                'max_stay' => $this->ratePlan->max_stay,
            ],
            'availability' => [
                'available_rooms' => $this->available_rooms,
                'total_rooms' => $this->total_rooms,
                'is_available' => $this->available_rooms > 0,
            ],
            'pricing' => [
                'base_price' => $this->base_price,
                'seasonal_rates' => $this->seasonal_rates, // Array of daily rates
                'total_price' => $this->total_price,
                'currency' => $this->currency,
                'taxes_included' => $this->taxes_included,
            ],
            'restrictions' => [
                'min_stay' => $this->min_stay,
                'max_stay' => $this->max_stay,
                'closed_to_arrival' => $this->closed_to_arrival ?? false,
                'closed_to_departure' => $this->closed_to_departure ?? false,
            ],
        ];
    }
}
