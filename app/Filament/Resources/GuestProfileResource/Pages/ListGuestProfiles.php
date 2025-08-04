<?php

namespace App\Filament\Resources\GuestProfileResource\Pages;

use App\Filament\Resources\GuestProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGuestProfiles extends ListRecords
{
    protected static string $resource = GuestProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
