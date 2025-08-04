<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResortResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'location' => $this->location,
            'island' => $this->island,
            'atoll' => $this->atoll,
            'coordinates' => $this->coordinates,
            'description' => $this->description, // Translatable field
            'star_rating' => $this->star_rating,
            'resort_type' => $this->resort_type,
            'currency' => $this->currency,
            'featured_image' => $this->featured_image,
            'gallery' => $this->getMedia('gallery')->map(function ($media) {
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
            'room_types' => $this->whenLoaded('roomTypes', function () {
                return RoomTypeResource::collection($this->roomTypes);
            }),
            'transfers' => $this->whenLoaded('transfers', function () {
                return $this->transfers->map(function ($transfer) {
                    return [
                        'id' => $transfer->id,
                        'name' => $transfer->name,
                        'type' => $transfer->type,
                        'route' => $transfer->route,
                        'price' => $transfer->price,
                        'capacity' => $transfer->capacity,
                    ];
                });
            }),
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'website' => $this->website,
            'active' => $this->active,
            'tax_rules' => $this->tax_rules, // JSON field
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
