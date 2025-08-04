<?php

namespace App\Filament\Resources\BookingItemResource\Pages;

use App\Filament\Resources\BookingItemResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBookingItem extends CreateRecord
{
    protected static string $resource = BookingItemResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Calculate total price if not provided
        if (empty($data['total_price']) && !empty($data['unit_price']) && !empty($data['quantity'])) {
            $total = $data['unit_price'] * $data['quantity'];
            if (!empty($data['nights'])) {
                $total *= $data['nights'];
            }
            if (!empty($data['discount_amount'])) {
                $total -= $data['discount_amount'];
            }
            $data['total_price'] = $total;
        }

        return $data;
    }
}
