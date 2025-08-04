<?php

namespace App\Filament\Resources\SeasonalRateResource\Pages;

use App\Filament\Resources\SeasonalRateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSeasonalRates extends ListRecords
{
    protected static string $resource = SeasonalRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
