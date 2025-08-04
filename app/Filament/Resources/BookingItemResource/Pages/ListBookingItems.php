<?php

namespace App\Filament\Resources\BookingItemResource\Pages;

use App\Filament\Resources\BookingItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBookingItems extends ListRecords
{
    protected static string $resource = BookingItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
