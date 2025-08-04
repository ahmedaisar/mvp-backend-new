<?php

namespace App\Filament\Resources\BookingItemResource\Pages;

use App\Filament\Resources\BookingItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBookingItem extends ViewRecord
{
    protected static string $resource = BookingItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
