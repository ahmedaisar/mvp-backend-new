<?php

namespace App\Filament\Resources\RatePlanResource\Pages;

use App\Filament\Resources\RatePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRatePlan extends ViewRecord
{
    protected static string $resource = RatePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
