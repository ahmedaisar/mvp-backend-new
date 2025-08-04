<?php

namespace App\Filament\Resources\ResortResource\Pages;

use App\Filament\Resources\ResortResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListResorts extends ListRecords
{
    protected static string $resource = ResortResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
