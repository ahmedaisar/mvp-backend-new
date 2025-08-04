<?php

namespace App\Filament\Resources\SeasonalRateResource\Pages;

use App\Filament\Resources\SeasonalRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSeasonalRate extends ViewRecord
{
    protected static string $resource = SeasonalRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
