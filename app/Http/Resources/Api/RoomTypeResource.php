<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
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
            'resort_id' => $this->resort_id,
            'code' => $this->code,
            'name' => $this->name, // Translatable field
            'description' => $this->description, // Translatable field
            'capacity_adults' => $this->capacity_adults,
            'capacity_children' => $this->capacity_children,
            'default_price' => $this->default_price,
            'size_sqm' => $this->size_sqm,
            'bed_configuration' => $this->bed_configuration,
            'room_view' => $this->room_view,
            'images' => $this->getMedia('images')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->getUrl('thumb'),
                    'alt' => $media->getCustomProperty('alt_text'),
                ];
            }),
            'amenities' => $this->whenLoaded('amenities', function () {
                return $this->amenities->map(function ($amenity) {
                    return [
                        'id' => $amenity->id,
                        'code' => $amenity->code,
                        'name' => $amenity->name, // Translatable
                    ];
                });
            }),
            'rate_plans' => $this->whenLoaded('ratePlans', function () {
                return $this->ratePlans->map(function ($ratePlan) {
                    return [
                        'id' => $ratePlan->id,
                        'name' => $ratePlan->name,
                        'refundable' => $ratePlan->refundable,
                        'breakfast_included' => $ratePlan->breakfast_included,
                        'cancellation_policy' => $ratePlan->cancellation_policy,
                        'deposit_required' => $ratePlan->deposit_required,
                        'min_stay' => $ratePlan->min_stay,
                        'max_stay' => $ratePlan->max_stay,
                    ];
                });
            }),
            'active' => $this->active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
