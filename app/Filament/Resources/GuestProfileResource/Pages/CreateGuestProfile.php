<?php

namespace App\Filament\Resources\GuestProfileResource\Pages;

use App\Filament\Resources\GuestProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGuestProfile extends CreateRecord
{
    protected static string $resource = GuestProfileResource::class;
}
