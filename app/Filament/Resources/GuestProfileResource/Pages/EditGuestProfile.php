<?php

namespace App\Filament\Resources\GuestProfileResource\Pages;

use App\Filament\Resources\GuestProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGuestProfile extends EditRecord
{
    protected static string $resource = GuestProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
