<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'booking_reference' => $this->booking_reference,
            'status' => $this->status,
            'guest_profile' => new GuestProfileResource($this->whenLoaded('guestProfile')),
            'resort' => [
                'id' => $this->resort->id,
                'name' => $this->resort->name,
                'location' => $this->resort->location,
                'contact_email' => $this->resort->contact_email,
                'contact_phone' => $this->resort->contact_phone,
            ],
            'room_type' => [
                'id' => $this->roomType->id,
                'code' => $this->roomType->code,
                'name' => $this->roomType->name,
            ],
            'rate_plan' => [
                'id' => $this->ratePlan->id,
                'name' => $this->ratePlan->name,
                'refundable' => $this->ratePlan->refundable,
                'breakfast_included' => $this->ratePlan->breakfast_included,
                'cancellation_policy' => $this->ratePlan->cancellation_policy,
            ],
            'dates' => [
                'check_in' => $this->check_in->format('Y-m-d'),
                'check_out' => $this->check_out->format('Y-m-d'),
                'nights' => $this->check_in->diffInDays($this->check_out),
            ],
            'guests' => [
                'adults' => $this->adults,
                'children' => $this->children,
                'additional_guests' => $this->additional_guests, // JSON field
            ],
            'pricing' => [
                'subtotal' => $this->subtotal_usd,
                'total_price_usd' => $this->total_price_usd,
                'currency_rate_usd' => $this->currency_rate_usd,
                'breakdown' => $this->pricing_breakdown, // JSON field with daily rates
            ],
            'promotion' => $this->when($this->promotion, [
                'id' => $this->promotion?->id,
                'code' => $this->promotion?->code,
                'description' => $this->promotion?->description,
                'discount_amount' => $this->discount_amount,
            ]),
            'transfer' => $this->when($this->transfer, [
                'id' => $this->transfer?->id,
                'name' => $this->transfer?->name,
                'type' => $this->transfer?->type,
                'price' => $this->transfer?->price,
            ]),
            'booking_items' => $this->whenLoaded('bookingItems', function () {
                return $this->bookingItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'description' => $item->description,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                    ];
                });
            }),
            'special_requests' => $this->special_requests,
            'payment_status' => $this->payment_status,
            'cancellation_reason' => $this->when($this->status === 'cancelled', $this->cancellation_reason),
            'cancelled_at' => $this->when($this->status === 'cancelled', $this->cancelled_at),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
