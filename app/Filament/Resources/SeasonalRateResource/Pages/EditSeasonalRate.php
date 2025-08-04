<?php

namespace App\Filament\Resources\SeasonalRateResource\Pages;

use App\Filament\Resources\SeasonalRateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSeasonalRate extends EditRecord
{
    protected static string $resource = SeasonalRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
