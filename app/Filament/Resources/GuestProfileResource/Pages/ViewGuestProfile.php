<?php

namespace App\Filament\Resources\GuestProfileResource\Pages;

use App\Filament\Resources\GuestProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGuestProfile extends ViewRecord
{
    protected static string $resource = GuestProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
